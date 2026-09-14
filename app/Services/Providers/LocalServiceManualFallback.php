<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LocalServiceManualFallback
{
    /**
     * Convert local API-linked services to Manual when a successful provider
     * catalog sync proves that their remote service no longer exists.
     *
     * The local service itself is preserved: active state, name, group, cost,
     * profit and customer/group pricing are not changed.
     *
     * @return array{converted_services:int,converted_orders:int,skipped_empty_catalog:bool,service_ids:array<int,int>}
     */
    public function reconcile(ApiProvider $provider, string $kind): array
    {
        $kind = strtolower(trim($kind));
        $map = $this->map($kind);

        $result = [
            'converted_services' => 0,
            'converted_orders' => 0,
            'skipped_empty_catalog' => false,
            'service_ids' => [],
        ];

        if ($map === null
            || !Schema::hasTable($map['services'])
            || !Schema::hasTable($map['remote'])) {
            return $result;
        }

        $serviceColumns = Schema::getColumnListing($map['services']);
        $remoteColumns = Schema::getColumnListing($map['remote']);

        if (!in_array('supplier_id', $serviceColumns, true)
            || !in_array('remote_id', $serviceColumns, true)
            || !in_array('api_provider_id', $remoteColumns, true)
            || !in_array('remote_id', $remoteColumns, true)) {
            return $result;
        }

        $remoteIds = DB::table($map['remote'])
            ->where('api_provider_id', (int)$provider->id)
            ->pluck('remote_id')
            ->map(fn ($id) => trim((string)$id))
            ->filter(fn (string $id) => $id !== '')
            ->unique()
            ->values();

        // Safety guard: a transient/invalid empty catalog response must never
        // mass-convert every linked local service to Manual in one pass.
        if ($remoteIds->isEmpty()) {
            $result['skipped_empty_catalog'] = true;
            return $result;
        }

        $select = array_values(array_intersect([
            'id', 'remote_id', 'params', 'active', 'source', 'supplier_id',
            'cost', 'profit', 'profit_type',
            'use_remote_cost', 'use_remote_price', 'stop_on_api_change',
        ], $serviceColumns));

        $linked = DB::table($map['services'])
            ->where('supplier_id', (int)$provider->id)
            ->whereNotNull('remote_id')
            ->where('remote_id', '<>', '')
            ->get($select);

        if ($linked->isEmpty()) {
            return $result;
        }

        $remoteSet = array_fill_keys($remoteIds->all(), true);
        $missing = $linked->filter(function ($service) use ($remoteSet): bool {
            $remoteId = trim((string)($service->remote_id ?? ''));
            return $remoteId !== '' && !isset($remoteSet[$remoteId]);
        })->values();

        if ($missing->isEmpty()) {
            return $result;
        }

        DB::transaction(function () use ($provider, $kind, $map, $serviceColumns, $missing, &$result): void {
            foreach ($missing as $service) {
                $serviceId = (int)($service->id ?? 0);
                $remoteId = trim((string)($service->remote_id ?? ''));
                if ($serviceId <= 0 || $remoteId === '') {
                    continue;
                }

                $update = [
                    'supplier_id' => null,
                    'remote_id' => null,
                ];

                if (in_array('source', $serviceColumns, true)) {
                    $update['source'] = 1; // Manual
                }
                if (in_array('use_remote_cost', $serviceColumns, true)) {
                    $update['use_remote_cost'] = 0;
                }
                if (in_array('use_remote_price', $serviceColumns, true)) {
                    $update['use_remote_price'] = 0;
                }
                if (in_array('stop_on_api_change', $serviceColumns, true)) {
                    $update['stop_on_api_change'] = 0;
                }
                if (in_array('params', $serviceColumns, true)) {
                    $params = $this->decodeArray($service->params ?? null);
                    $params['provider_manual_fallback'] = [
                        'provider_id' => (int)$provider->id,
                        'provider_name' => (string)($provider->name ?? ''),
                        'provider_type' => (string)($provider->type ?? ''),
                        'remote_id' => $remoteId,
                        'kind' => $kind,
                        'reason' => 'remote_service_removed',
                        'converted_at' => now()->toDateTimeString(),
                    ];
                    $update['params'] = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                if (in_array('updated_at', $serviceColumns, true)) {
                    $update['updated_at'] = now();
                }

                // Re-check the original mapping inside the transaction so a
                // concurrent admin relink is never overwritten.
                $changed = DB::table($map['services'])
                    ->where('id', $serviceId)
                    ->where('supplier_id', (int)$provider->id)
                    ->where('remote_id', $remoteId)
                    ->update($update);

                if ($changed < 1) {
                    continue;
                }

                $result['converted_services']++;
                $result['service_ids'][] = $serviceId;
                $result['converted_orders'] += $this->convertSafeWaitingOrders(
                    $provider,
                    $kind,
                    $map['orders'],
                    $serviceId,
                    $remoteId
                );

                Log::warning('Provider service removed; local service converted to manual', [
                    'kind' => $kind,
                    'service_id' => $serviceId,
                    'provider_id' => (int)$provider->id,
                    'remote_id' => $remoteId,
                ]);
            }
        });

        return $result;
    }

    private function convertSafeWaitingOrders(
        ApiProvider $provider,
        string $kind,
        string $ordersTable,
        int $serviceId,
        string $previousRemoteId
    ): int {
        if (!Schema::hasTable($ordersTable)) {
            return 0;
        }

        $columns = Schema::getColumnListing($ordersTable);
        foreach (['id', 'service_id', 'status', 'remote_id', 'api_order', 'processing'] as $required) {
            if (!in_array($required, $columns, true)) {
                return 0;
            }
        }

        $select = array_values(array_intersect(['id', 'request', 'response'], $columns));

        $orders = DB::table($ordersTable)
            ->where('service_id', $serviceId)
            ->where('api_order', 1)
            ->where('status', 'waiting')
            ->where(function ($q): void {
                $q->whereNull('remote_id')->orWhere('remote_id', '');
            })
            ->where(function ($q): void {
                $q->whereNull('processing')->orWhere('processing', 0)->orWhere('processing', false);
            })
            ->get($select);

        $converted = 0;

        foreach ($orders as $order) {
            $request = $this->decodeArray($order->request ?? null);
            if (!empty($request['dispatch_hold']) || !empty(data_get($request, 'request.dispatch_hold'))) {
                continue;
            }

            $request['manual_fallback'] = [
                'provider_id' => (int)$provider->id,
                'provider_name' => (string)($provider->name ?? ''),
                'provider_type' => (string)($provider->type ?? ''),
                'previous_remote_service_id' => $previousRemoteId,
                'kind' => $kind,
                'reason' => 'remote_service_removed',
                'converted_at' => now()->toDateTimeString(),
            ];

            $update = [
                'api_order' => 0,
                'processing' => 0,
            ];
            if (in_array('supplier_id', $columns, true)) {
                $update['supplier_id'] = null;
            }
            if (in_array('request', $columns, true)) {
                $update['request'] = json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (in_array('response', $columns, true)) {
                $response = $this->decodeArray($order->response ?? null);
                $response['type'] = 'info';
                $response['message'] = 'SERVICE MOVED TO MANUAL - provider removed remote service';
                $update['response'] = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (in_array('updated_at', $columns, true)) {
                $update['updated_at'] = now();
            }

            $changed = DB::table($ordersTable)
                ->where('id', (int)$order->id)
                ->where('api_order', 1)
                ->where('status', 'waiting')
                ->where(function ($q): void {
                    $q->whereNull('remote_id')->orWhere('remote_id', '');
                })
                ->where(function ($q): void {
                    $q->whereNull('processing')->orWhere('processing', 0)->orWhere('processing', false);
                })
                ->update($update);

            if ($changed > 0) {
                $converted++;
            }
        }

        return $converted;
    }

    private function map(string $kind): ?array
    {
        return match ($kind) {
            'imei' => ['services' => 'imei_services', 'remote' => 'remote_imei_services', 'orders' => 'imei_orders'],
            'server' => ['services' => 'server_services', 'remote' => 'remote_server_services', 'orders' => 'server_orders'],
            'file' => ['services' => 'file_services', 'remote' => 'remote_file_services', 'orders' => 'file_orders'],
            'smm' => ['services' => 'smm_services', 'remote' => 'remote_smm_services', 'orders' => 'smm_orders'],
            default => null,
        };
    }

    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
