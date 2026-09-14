<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditServiceGroupPrices extends Command
{
    protected $signature = 'services:group-price-audit {--details=50 : Maximum risky rows to display}';

    protected $description = 'Read-only audit for service group pricing references and invalid discount values.';

    public function handle(): int
    {
        if (!Schema::hasTable('service_group_prices')) {
            $this->error('service_group_prices table is missing.');
            return self::FAILURE;
        }

        $maps = [
            'imei' => 'imei_services',
            'server' => 'server_services',
            'file' => 'file_services',
            'smm' => 'smm_services',
        ];

        $state = [
            'rows_scanned' => 0,
            'invalid_service_type' => 0,
            'missing_service' => 0,
            'missing_customer_group' => 0,
            'invalid_discount_type' => 0,
            'negative_price' => 0,
            'negative_discount' => 0,
            'percent_discount_over_100' => 0,
            'effective_price_negative' => 0,
        ];

        $details = [];
        $limit = max(0, min(200, (int)$this->option('details')));

        $serviceIds = [];
        foreach ($maps as $kind => $table) {
            $serviceIds[$kind] = Schema::hasTable($table)
                ? DB::table($table)->pluck('id')->map(fn ($id) => (int)$id)->flip()->all()
                : [];
        }

        $groupIds = Schema::hasTable('groups')
            ? DB::table('groups')->pluck('id')->map(fn ($id) => (int)$id)->flip()->all()
            : [];

        $rows = DB::table('service_group_prices')
            ->orderBy('id')
            ->get(['id', 'service_id', 'service_type', 'group_id', 'price', 'discount', 'discount_type']);

        $state['rows_scanned'] = $rows->count();

        foreach ($rows as $row) {
            $kind = strtolower(trim((string)$row->service_type));
            $serviceId = (int)$row->service_id;
            $groupId = (int)$row->group_id;
            $price = (float)$row->price;
            $discount = (float)$row->discount;
            $discountType = (int)$row->discount_type;

            $risks = [];

            if (!array_key_exists($kind, $maps)) {
                $state['invalid_service_type']++;
                $risks[] = 'invalid_service_type';
            } elseif (!isset($serviceIds[$kind][$serviceId])) {
                $state['missing_service']++;
                $risks[] = 'missing_service';
            }

            if (!isset($groupIds[$groupId])) {
                $state['missing_customer_group']++;
                $risks[] = 'missing_customer_group';
            }

            if (!in_array($discountType, [1, 2], true)) {
                $state['invalid_discount_type']++;
                $risks[] = 'invalid_discount_type';
            }

            if ($price < 0) {
                $state['negative_price']++;
                $risks[] = 'negative_price';
            }

            if ($discount < 0) {
                $state['negative_discount']++;
                $risks[] = 'negative_discount';
            }

            if ($discountType === 2 && $discount > 100) {
                $state['percent_discount_over_100']++;
                $risks[] = 'percent_discount_over_100';
            }

            $effective = $discountType === 2
                ? $price - ($price * ($discount / 100))
                : $price - $discount;

            if ($effective < -0.000001) {
                $state['effective_price_negative']++;
                $risks[] = 'effective_price_negative';
            }

            if ($risks !== [] && count($details) < $limit) {
                $details[] = [
                    'id' => (int)$row->id,
                    'type' => $kind !== '' ? $kind : '—',
                    'service_id' => $serviceId,
                    'group_id' => $groupId,
                    'price' => number_format($price, 4, '.', ''),
                    'discount' => number_format($discount, 4, '.', ''),
                    'discount_type' => $discountType,
                    'risk' => implode(',', $risks),
                ];
            }
        }

        $this->info('Read-only service group price audit');
        $this->table(
            ['State', 'Count'],
            collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        if ($details !== []) {
            $this->newLine();
            $this->warn('Group price risks (read-only):');
            $this->table(
                ['ID', 'Type', 'Service', 'Group', 'Price', 'Discount', 'Discount type', 'Risk'],
                array_map(fn (array $r) => [
                    $r['id'], $r['type'], $r['service_id'], $r['group_id'],
                    $r['price'], $r['discount'], $r['discount_type'], $r['risk'],
                ], $details)
            );
        }

        $problemKeys = [
            'invalid_service_type',
            'missing_service',
            'missing_customer_group',
            'invalid_discount_type',
            'negative_price',
            'negative_discount',
            'percent_discount_over_100',
            'effective_price_negative',
        ];

        $hasProblems = collect($problemKeys)->contains(fn ($key) => $state[$key] > 0);

        return $hasProblems ? self::FAILURE : self::SUCCESS;
    }
}
