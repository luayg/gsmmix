<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditOrderProviderReferences extends Command
{
    protected $signature = 'orders:provider-reference-audit
        {--details=50 : Maximum risky rows to display}
        {--json : Output JSON instead of tables}';

    protected $description = 'Read-only audit of order supplier/provider references across IMEI, Server, File, and SMM orders.';

    public function handle(): int
    {
        $tables = [
            'imei' => 'imei_orders',
            'server' => 'server_orders',
            'file' => 'file_orders',
            'smm' => 'smm_orders',
        ];

        $state = [
            'order_tables_scanned' => 0,
            'orders_scanned' => 0,
            'orders_with_supplier_id' => 0,
            'active_api_order_without_supplier' => 0,
            'active_order_provider_missing' => 0,
            'active_order_provider_disabled' => 0,
            'historical_order_provider_missing' => 0,
            'manual_waiting_order_with_supplier' => 0,
        ];

        $risks = [];
        $limit = max(0, min(200, (int)$this->option('details')));

        $providers = [];
        if (Schema::hasTable('api_providers')) {
            foreach (DB::table('api_providers')->get(['id', 'active']) as $provider) {
                $providers[(int)$provider->id] = (int)($provider->active ?? 0) === 1;
            }
        }

        foreach ($tables as $kind => $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            if (!in_array('id', $columns, true)
                || !in_array('status', $columns, true)
                || !in_array('api_order', $columns, true)
                || !in_array('supplier_id', $columns, true)) {
                continue;
            }

            $state['order_tables_scanned']++;
            $select = array_values(array_intersect([
                'id', 'status', 'api_order', 'supplier_id', 'remote_id', 'service_id', 'processing',
            ], $columns));

            $orders = DB::table($table)->get($select);
            $state['orders_scanned'] += $orders->count();

            foreach ($orders as $order) {
                $status = strtolower(trim((string)($order->status ?? '')));
                $active = in_array($status, ['waiting', 'inprogress'], true);
                $waiting = $status === 'waiting';
                $apiOrder = (int)($order->api_order ?? 0) === 1;
                $supplierId = (int)($order->supplier_id ?? 0);

                if ($supplierId > 0) {
                    $state['orders_with_supplier_id']++;
                }

                if ($active && $apiOrder && $supplierId <= 0) {
                    $state['active_api_order_without_supplier']++;
                    $this->addRisk($risks, $limit, $kind, (int)$order->id, $status, 'active_api_order_without_supplier', $supplierId);
                    continue;
                }

                if ($supplierId > 0 && !array_key_exists($supplierId, $providers)) {
                    if ($active) {
                        $state['active_order_provider_missing']++;
                        $this->addRisk($risks, $limit, $kind, (int)$order->id, $status, 'active_order_provider_missing', $supplierId);
                    } else {
                        $state['historical_order_provider_missing']++;
                    }
                    continue;
                }

                if ($active && $supplierId > 0 && ($providers[$supplierId] ?? false) === false) {
                    $state['active_order_provider_disabled']++;
                    $this->addRisk($risks, $limit, $kind, (int)$order->id, $status, 'active_order_provider_disabled', $supplierId);
                }

                if ($waiting && !$apiOrder && $supplierId > 0) {
                    $state['manual_waiting_order_with_supplier']++;
                    $this->addRisk($risks, $limit, $kind, (int)$order->id, $status, 'manual_waiting_order_with_supplier', $supplierId);
                }
            }
        }

        if ($this->option('json')) {
            $this->line((string)json_encode([
                'state' => $state,
                'risks' => $risks,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Read-only order provider reference audit');
            $this->table(
                ['State', 'Count'],
                collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all()
            );

            if ($risks !== []) {
                $this->newLine();
                $this->warn('Provider reference risks (read-only):');
                $this->table(
                    ['Kind', 'Order ID', 'Status', 'Risk', 'Provider'],
                    array_map(fn (array $row) => [
                        $row['kind'], $row['order_id'], $row['status'], $row['risk'], $row['provider_id'],
                    ], $risks)
                );
            }
        }

        $unsafe = $state['active_api_order_without_supplier'] > 0
            || $state['active_order_provider_missing'] > 0
            || $state['active_order_provider_disabled'] > 0
            || $state['manual_waiting_order_with_supplier'] > 0;

        return $unsafe ? self::FAILURE : self::SUCCESS;
    }

    private function addRisk(
        array &$risks,
        int $limit,
        string $kind,
        int $orderId,
        string $status,
        string $risk,
        int $providerId
    ): void {
        if ($limit <= 0 || count($risks) >= $limit) {
            return;
        }

        $risks[] = [
            'kind' => $kind,
            'order_id' => $orderId,
            'status' => $status !== '' ? $status : '-',
            'risk' => $risk,
            'provider_id' => $providerId > 0 ? $providerId : '-',
        ];
    }
}
