<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AuditServicePricing extends Command
{
    protected $signature = 'services:pricing-audit
        {--details=20 : Maximum risky rows to display}
        {--json : Output JSON instead of tables}';

    protected $description = 'Read-only audit of local sell prices, group prices, and current provider catalog costs.';

    public function handle(): int
    {
        $kinds = [
            'imei' => ['local' => 'imei_services', 'remote' => 'remote_imei_services'],
            'server' => ['local' => 'server_services', 'remote' => 'remote_server_services'],
            'file' => ['local' => 'file_services', 'remote' => 'remote_file_services'],
            'smm' => ['local' => 'smm_services', 'remote' => 'remote_smm_services'],
        ];

        $state = [
            'service_tables_scanned' => 0,
            'api_services_mapped' => 0,
            'missing_remote_mapping' => 0,
            'remote_cost_changed' => 0,
            'remote_cost_higher' => 0,
            'main_sell_below_remote_cost' => 0,
            'group_sell_below_remote_cost' => 0,
            'use_remote_cost_drift' => 0,
            'use_remote_price_drift' => 0,
            'stop_on_api_change_not_stopped' => 0,
        ];

        $risks = [];
        $detailLimit = max(0, min(200, (int)$this->option('details')));
        $epsilon = 0.00005;

        foreach ($kinds as $kind => $tables) {
            if (!Schema::hasTable($tables['local']) || !Schema::hasTable($tables['remote'])) {
                continue;
            }

            $state['service_tables_scanned']++;

            $localColumns = Schema::getColumnListing($tables['local']);
            $remoteColumns = Schema::getColumnListing($tables['remote']);

            if (!in_array('supplier_id', $localColumns, true)
                || !in_array('remote_id', $localColumns, true)
                || !in_array('api_provider_id', $remoteColumns, true)
                || !in_array('remote_id', $remoteColumns, true)
                || !in_array('price', $remoteColumns, true)) {
                continue;
            }

            $localSelect = array_values(array_intersect([
                'id', 'supplier_id', 'remote_id', 'active',
                'cost', 'profit', 'profit_type',
                'price', 'sell_price', 'final_price', 'customer_price', 'retail_price',
                'use_remote_cost', 'use_remote_price', 'stop_on_api_change',
            ], $localColumns));

            $locals = DB::table($tables['local'])
                ->whereNotNull('supplier_id')
                ->whereNotNull('remote_id')
                ->where('remote_id', '<>', '')
                ->get($localSelect);

            if ($locals->isEmpty()) {
                continue;
            }

            $providerIds = $locals->pluck('supplier_id')->map(fn ($id) => (int)$id)->filter()->unique()->values()->all();
            $remotes = DB::table($tables['remote'])
                ->whereIn('api_provider_id', $providerIds)
                ->get(['api_provider_id', 'remote_id', 'price']);

            $remoteMap = [];
            foreach ($remotes as $remote) {
                $remoteMap[$this->remoteKey($remote->api_provider_id ?? 0, $remote->remote_id ?? '')] = $remote;
            }

            $groupRowsByService = [];
            if (Schema::hasTable('service_group_prices')) {
                $serviceIds = $locals->pluck('id')->map(fn ($id) => (int)$id)->filter()->values()->all();
                if ($serviceIds !== []) {
                    $groupRows = DB::table('service_group_prices')
                        ->where('service_type', $kind)
                        ->whereIn('service_id', $serviceIds)
                        ->get(['service_id', 'group_id', 'price', 'discount', 'discount_type']);

                    foreach ($groupRows as $row) {
                        $groupRowsByService[(int)$row->service_id][] = $row;
                    }
                }
            }

            foreach ($locals as $local) {
                $serviceId = (int)($local->id ?? 0);
                $providerId = (int)($local->supplier_id ?? 0);
                $remoteId = trim((string)($local->remote_id ?? ''));
                $remote = $remoteMap[$this->remoteKey($providerId, $remoteId)] ?? null;

                if (!$remote) {
                    $state['missing_remote_mapping']++;
                    $this->addRisk($risks, $detailLimit, $kind, $serviceId, $providerId, $remoteId, 'missing_remote', null, null, null);
                    continue;
                }

                $state['api_services_mapped']++;

                $remoteCost = $this->number($remote->price ?? 0);
                $localCost = $this->number($local->cost ?? 0);
                $mainSell = $this->mainSellPrice($local, $localCost);
                $costDiff = abs($remoteCost - $localCost);
                $remoteHigher = $remoteCost > ($localCost + $epsilon);

                if ($costDiff > $epsilon) {
                    $state['remote_cost_changed']++;
                }
                if ($remoteHigher) {
                    $state['remote_cost_higher']++;
                }
                if ($remoteCost > ($mainSell + $epsilon)) {
                    $state['main_sell_below_remote_cost']++;
                    $this->addRisk($risks, $detailLimit, $kind, $serviceId, $providerId, $remoteId, 'main_below_remote', $localCost, $remoteCost, $mainSell);
                }

                if ((int)($local->use_remote_cost ?? 0) === 1 && $costDiff > $epsilon) {
                    $state['use_remote_cost_drift']++;
                    $this->addRisk($risks, $detailLimit, $kind, $serviceId, $providerId, $remoteId, 'use_remote_cost_drift', $localCost, $remoteCost, $mainSell);
                }

                if ((int)($local->use_remote_price ?? 0) === 1 && $costDiff > $epsilon) {
                    $state['use_remote_price_drift']++;
                    $this->addRisk($risks, $detailLimit, $kind, $serviceId, $providerId, $remoteId, 'use_remote_price_drift', $localCost, $remoteCost, $mainSell);
                }

                if ((int)($local->stop_on_api_change ?? 0) === 1
                    && (int)($local->active ?? 1) === 1
                    && $remoteHigher) {
                    $state['stop_on_api_change_not_stopped']++;
                    $this->addRisk($risks, $detailLimit, $kind, $serviceId, $providerId, $remoteId, 'stop_flag_not_applied', $localCost, $remoteCost, $mainSell);
                }

                foreach ($groupRowsByService[$serviceId] ?? [] as $groupRow) {
                    $effective = $this->effectiveGroupPrice($groupRow);
                    if ($remoteCost > ($effective + $epsilon)) {
                        $state['group_sell_below_remote_cost']++;
                        $this->addRisk(
                            $risks,
                            $detailLimit,
                            $kind,
                            $serviceId,
                            $providerId,
                            $remoteId,
                            'group_below_remote:g' . (int)($groupRow->group_id ?? 0),
                            $localCost,
                            $remoteCost,
                            $effective
                        );
                    }
                }
            }
        }

        if ($this->option('json')) {
            $this->line((string)json_encode([
                'state' => $state,
                'risks' => $risks,
            ], JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Read-only service pricing audit');
            $this->table(
                ['State', 'Count'],
                collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all()
            );

            if ($risks !== []) {
                $this->newLine();
                $this->warn('Pricing risks (read-only):');
                $this->table(
                    ['Kind', 'Service', 'Provider', 'Remote ID', 'Risk', 'Local cost', 'Remote cost', 'Sell/effective'],
                    array_map(fn (array $row) => [
                        $row['kind'],
                        $row['service_id'],
                        $row['provider_id'],
                        $row['remote_id'],
                        $row['risk'],
                        $row['local_cost'],
                        $row['remote_cost'],
                        $row['sell_price'],
                    ], $risks)
                );
            }
        }

        $unsafe = $state['main_sell_below_remote_cost'] > 0
            || $state['group_sell_below_remote_cost'] > 0
            || $state['use_remote_cost_drift'] > 0
            || $state['use_remote_price_drift'] > 0
            || $state['stop_on_api_change_not_stopped'] > 0;

        return $unsafe ? self::FAILURE : self::SUCCESS;
    }

    private function mainSellPrice(object $service, float $cost): float
    {
        foreach (['price', 'sell_price', 'final_price', 'customer_price', 'retail_price'] as $column) {
            if (property_exists($service, $column) && is_numeric($service->{$column}) && (float)$service->{$column} > 0) {
                return max(0.0, (float)$service->{$column});
            }
        }

        $profit = $this->number($service->profit ?? 0);
        $profitType = (int)($service->profit_type ?? 1);
        $price = $profitType === 2
            ? $cost + ($cost * ($profit / 100))
            : $cost + $profit;

        return is_finite($price) ? max(0.0, $price) : 0.0;
    }

    private function effectiveGroupPrice(object $row): float
    {
        $price = $this->number($row->price ?? 0);
        $discount = max(0.0, $this->number($row->discount ?? 0));
        $discountType = (int)($row->discount_type ?? 1);

        if ($discount > 0) {
            $price = $discountType === 2
                ? $price - ($price * ($discount / 100))
                : $price - $discount;
        }

        return is_finite($price) ? max(0.0, $price) : 0.0;
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float)$value : 0.0;
    }

    private function remoteKey(mixed $providerId, mixed $remoteId): string
    {
        return (int)$providerId . '|' . trim((string)$remoteId);
    }

    private function addRisk(
        array &$risks,
        int $limit,
        string $kind,
        int $serviceId,
        int $providerId,
        string $remoteId,
        string $risk,
        ?float $localCost,
        ?float $remoteCost,
        ?float $sellPrice
    ): void {
        if ($limit <= 0 || count($risks) >= $limit) {
            return;
        }

        $format = static fn (?float $value): string => $value === null ? '-' : number_format($value, 4, '.', '');

        $risks[] = [
            'kind' => $kind,
            'service_id' => $serviceId,
            'provider_id' => $providerId,
            'remote_id' => $remoteId,
            'risk' => $risk,
            'local_cost' => $format($localCost),
            'remote_cost' => $format($remoteCost),
            'sell_price' => $format($sellPrice),
        ];
    }
}
