<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditServiceReferences extends Command
{
    protected $signature = 'services:reference-audit {--details=30 : Maximum orphan rows to display}';

    protected $description = 'Read-only audit for order rows that reference missing local services.';

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
            'orders_scanned' => 0,
            'orders_with_service_id' => 0,
            'orphan_order_service_refs' => 0,
        ];

        $details = [];
        $limit = max(0, min(200, (int)$this->option('details')));

        foreach ($maps as $kind => $map) {
            if (!Schema::hasTable($map['services']) || !Schema::hasTable($map['orders'])) {
                continue;
            }

            if (!Schema::hasColumn($map['orders'], 'service_id')) {
                continue;
            }

            $state['service_tables_scanned']++;
            $state['order_tables_scanned']++;

            $orders = DB::table($map['orders'])->get(['id', 'service_id']);
            $state['orders_scanned'] += $orders->count();

            $serviceIds = DB::table($map['services'])
                ->pluck('id')
                ->map(fn ($id) => (int)$id)
                ->flip()
                ->all();

            foreach ($orders as $order) {
                $serviceId = (int)($order->service_id ?? 0);
                if ($serviceId <= 0) {
                    continue;
                }

                $state['orders_with_service_id']++;

                if (!isset($serviceIds[$serviceId])) {
                    $state['orphan_order_service_refs']++;
                    if (count($details) < $limit) {
                        $details[] = [
                            'kind' => $kind,
                            'order_id' => (int)$order->id,
                            'service_id' => $serviceId,
                        ];
                    }
                }
            }
        }

        $this->info('Read-only service reference audit');
        $this->table(
            ['State', 'Count'],
            collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        if ($details !== []) {
            $this->newLine();
            $this->warn('Orphan service references (read-only):');
            $this->table(
                ['Kind', 'Order ID', 'Missing service ID'],
                array_map(fn (array $row) => [$row['kind'], $row['order_id'], $row['service_id']], $details)
            );
        }

        return $state['orphan_order_service_refs'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
