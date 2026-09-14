<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditServiceRouting extends Command
{
    protected $signature = 'services:routing-audit
        {--details=30 : Maximum risky rows to display}
        {--json : Output JSON instead of tables}';

    protected $description = 'Read-only audit of Manual/API service routing and order dispatch consistency.';

    public function handle(): int
    {
        $maps = [
            'imei' => ['services' => 'imei_services', 'orders' => 'imei_orders'],
            'server' => ['services' => 'server_services', 'orders' => 'server_orders'],
            'file' => ['services' => 'file_services', 'orders' => 'file_orders'],
            'smm' => ['services' => 'smm_services', 'orders' => 'smm_orders'],
        ];

        $state = [
            'service_tables_scanned' => 0,
            'order_tables_scanned' => 0,
            'services_total' => 0,
            'orders_total' => 0,
            'manual_service_with_provider_link' => 0,
            'api_service_missing_provider' => 0,
            'api_service_missing_remote_id' => 0,
            'service_provider_missing' => 0,
            'service_provider_disabled' => 0,
            'api_order_on_manual_service' => 0,
            'manual_order_on_api_service' => 0,
            'waiting_api_order_without_provider' => 0,
            'waiting_api_order_without_remote_service' => 0,
            'waiting_manual_order_with_provider_link' => 0,
        ];

        $details = [];
        $limit = max(0, min(200, (int)$this->option('details')));

        foreach ($maps as $kind => $map) {
            if (!Schema::hasTable($map['services'])) {
                continue;
            }

            $state['service_tables_scanned']++;
            $serviceColumns = Schema::getColumnListing($map['services']);
            $select = array_values(array_intersect([
                'id', 'name', 'source', 'active', 'supplier_id', 'remote_id',
            ], $serviceColumns));

            $services = DB::table($map['services'])->get($select);
            $state['services_total'] += $services->count();

            $serviceById = [];
            foreach ($services as $service) {
                $serviceById[(int)$service->id] = $service;

                $source = in_array('source', $serviceColumns, true)
                    ? (int)($service->source ?? 1)
                    : ((int)($service->supplier_id ?? 0) > 0 || trim((string)($service->remote_id ?? '')) !== '' ? 2 : 1);
                $supplierId = (int)($service->supplier_id ?? 0);
                $remoteId = trim((string)($service->remote_id ?? ''));

                if ($source === 1 && ($supplierId > 0 || $remoteId !== '')) {
                    $state['manual_service_with_provider_link']++;
                    $this->detail($details, $limit, $kind, 'service', (int)$service->id, 'manual_service_with_provider_link', $supplierId, $remoteId);
                }

                if ($source === 2) {
                    if ($supplierId <= 0) {
                        $state['api_service_missing_provider']++;
                        $this->detail($details, $limit, $kind, 'service', (int)$service->id, 'api_service_missing_provider', $supplierId, $remoteId);
                    }
                    if ($remoteId === '') {
                        $state['api_service_missing_remote_id']++;
                        $this->detail($details, $limit, $kind, 'service', (int)$service->id, 'api_service_missing_remote_id', $supplierId, $remoteId);
                    }
                }

                if ($supplierId > 0 && Schema::hasTable('api_providers')) {
                    $provider = DB::table('api_providers')->where('id', $supplierId)->first(['id', 'active']);
                    if (!$provider) {
                        $state['service_provider_missing']++;
                        $this->detail($details, $limit, $kind, 'service', (int)$service->id, 'service_provider_missing', $supplierId, $remoteId);
                    } elseif ((int)($provider->active ?? 0) !== 1 && $source === 2) {
                        $state['service_provider_disabled']++;
                        $this->detail($details, $limit, $kind, 'service', (int)$service->id, 'service_provider_disabled', $supplierId, $remoteId);
                    }
                }
            }

            if (!Schema::hasTable($map['orders'])) {
                continue;
            }

            $state['order_tables_scanned']++;
            $orderColumns = Schema::getColumnListing($map['orders']);
            $required = ['id', 'service_id', 'api_order', 'status'];
            if (array_diff($required, $orderColumns) !== []) {
                continue;
            }

            $orderSelect = array_values(array_intersect([
                'id', 'service_id', 'supplier_id', 'remote_id', 'api_order', 'status', 'processing',
            ], $orderColumns));
            $orders = DB::table($map['orders'])->get($orderSelect);
            $state['orders_total'] += $orders->count();

            foreach ($orders as $order) {
                $service = $serviceById[(int)($order->service_id ?? 0)] ?? null;
                if (!$service) {
                    continue;
                }

                $serviceSource = in_array('source', $serviceColumns, true)
                    ? (int)($service->source ?? 1)
                    : ((int)($service->supplier_id ?? 0) > 0 || trim((string)($service->remote_id ?? '')) !== '' ? 2 : 1);
                $serviceSupplierId = (int)($service->supplier_id ?? 0);
                $serviceRemoteId = trim((string)($service->remote_id ?? ''));
                $apiOrder = (int)($order->api_order ?? 0) === 1;
                $status = strtolower(trim((string)($order->status ?? '')));
                $waiting = $status === 'waiting';

                if ($apiOrder && $serviceSource === 1) {
                    $state['api_order_on_manual_service']++;
                    $this->detail($details, $limit, $kind, 'order', (int)$order->id, 'api_order_on_manual_service', $serviceSupplierId, $serviceRemoteId);
                }

                if (!$apiOrder && $serviceSource === 2 && $waiting) {
                    $state['manual_order_on_api_service']++;
                    $this->detail($details, $limit, $kind, 'order', (int)$order->id, 'manual_order_on_api_service', $serviceSupplierId, $serviceRemoteId);
                }

                if ($waiting && $apiOrder) {
                    if ($serviceSupplierId <= 0) {
                        $state['waiting_api_order_without_provider']++;
                        $this->detail($details, $limit, $kind, 'order', (int)$order->id, 'waiting_api_order_without_provider', $serviceSupplierId, $serviceRemoteId);
                    }
                    if ($serviceRemoteId === '') {
                        $state['waiting_api_order_without_remote_service']++;
                        $this->detail($details, $limit, $kind, 'order', (int)$order->id, 'waiting_api_order_without_remote_service', $serviceSupplierId, $serviceRemoteId);
                    }
                }

                if ($waiting && !$apiOrder) {
                    $orderSupplierId = (int)($order->supplier_id ?? 0);
                    $orderRemoteId = trim((string)($order->remote_id ?? ''));
                    if ($orderSupplierId > 0 || $orderRemoteId !== '') {
                        $state['waiting_manual_order_with_provider_link']++;
                        $this->detail($details, $limit, $kind, 'order', (int)$order->id, 'waiting_manual_order_with_provider_link', $orderSupplierId, $orderRemoteId);
                    }
                }
            }
        }

        if ($this->option('json')) {
            $this->line((string)json_encode(['state' => $state, 'risks' => $details], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Read-only service routing audit');
            $this->table(
                ['State', 'Count'],
                collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all()
            );

            if ($details !== []) {
                $this->newLine();
                $this->warn('Routing risks (read-only):');
                $this->table(
                    ['Kind', 'Entity', 'ID', 'Risk', 'Provider', 'Remote ID'],
                    array_map(fn (array $row) => [
                        $row['kind'], $row['entity'], $row['id'], $row['risk'], $row['provider_id'], $row['remote_id'],
                    ], $details)
                );
            }
        }

        $unsafeKeys = [
            'manual_service_with_provider_link',
            'api_service_missing_provider',
            'api_service_missing_remote_id',
            'service_provider_missing',
            'api_order_on_manual_service',
            'waiting_api_order_without_provider',
            'waiting_api_order_without_remote_service',
            'waiting_manual_order_with_provider_link',
        ];

        foreach ($unsafeKeys as $key) {
            if (($state[$key] ?? 0) > 0) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function detail(array &$details, int $limit, string $kind, string $entity, int $id, string $risk, int $providerId, string $remoteId): void
    {
        if ($limit <= 0 || count($details) >= $limit) {
            return;
        }

        $details[] = [
            'kind' => $kind,
            'entity' => $entity,
            'id' => $id,
            'risk' => $risk,
            'provider_id' => $providerId > 0 ? $providerId : '-',
            'remote_id' => $remoteId !== '' ? $remoteId : '-',
        ];
    }
}
