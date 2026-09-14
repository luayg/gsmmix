<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditProviderCatalog extends Command
{
    protected $signature = 'providers:catalog-audit {--details=50 : Maximum risky rows to display}';

    protected $description = 'Read-only audit of cached remote provider catalogs across IMEI, Server, File, and SMM services.';

    public function handle(): int
    {
        $tables = [
            'imei' => 'remote_imei_services',
            'server' => 'remote_server_services',
            'file' => 'remote_file_services',
            'smm' => 'remote_smm_services',
        ];

        $state = [
            'catalog_tables_scanned' => 0,
            'remote_services_scanned' => 0,
            'empty_remote_id' => 0,
            'provider_missing' => 0,
            'negative_price' => 0,
            'duplicate_provider_remote_id' => 0,
            'smm_min_over_max' => 0,
        ];

        $providers = Schema::hasTable('api_providers')
            ? DB::table('api_providers')->pluck('id')->map(fn ($id) => (int)$id)->flip()->all()
            : [];

        $details = [];
        $detailLimit = max(0, min(200, (int)$this->option('details')));

        foreach ($tables as $kind => $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $state['catalog_tables_scanned']++;
            $columns = Schema::getColumnListing($table);
            $select = array_values(array_intersect([
                'id', 'api_provider_id', 'remote_id', 'name', 'price', 'min', 'max',
            ], $columns));

            if (!in_array('id', $select, true)) {
                continue;
            }

            $rows = DB::table($table)->orderBy('id')->get($select);
            $state['remote_services_scanned'] += $rows->count();

            $seen = [];
            foreach ($rows as $row) {
                $risks = [];
                $providerId = (int)($row->api_provider_id ?? 0);
                $remoteId = trim((string)($row->remote_id ?? ''));

                if ($remoteId === '') {
                    $state['empty_remote_id']++;
                    $risks[] = 'empty_remote_id';
                }

                if ($providerId <= 0 || !isset($providers[$providerId])) {
                    $state['provider_missing']++;
                    $risks[] = 'provider_missing';
                }

                if (in_array('price', $columns, true) && isset($row->price) && (float)$row->price < 0) {
                    $state['negative_price']++;
                    $risks[] = 'negative_price';
                }

                if ($providerId > 0 && $remoteId !== '') {
                    $key = $providerId . '|' . $remoteId;
                    if (isset($seen[$key])) {
                        $state['duplicate_provider_remote_id']++;
                        $risks[] = 'duplicate_provider_remote_id';
                    } else {
                        $seen[$key] = true;
                    }
                }

                if ($kind === 'smm'
                    && in_array('min', $columns, true)
                    && in_array('max', $columns, true)
                    && (int)($row->min ?? 0) > 0
                    && (int)($row->max ?? 0) > 0
                    && (int)$row->min > (int)$row->max) {
                    $state['smm_min_over_max']++;
                    $risks[] = 'smm_min_over_max';
                }

                if ($risks !== [] && count($details) < $detailLimit) {
                    $details[] = [
                        'kind' => $kind,
                        'id' => (int)$row->id,
                        'provider' => $providerId ?: '—',
                        'remote_id' => $remoteId !== '' ? $remoteId : '—',
                        'risk' => implode(',', $risks),
                    ];
                }
            }
        }

        $this->info('Read-only provider catalog audit');
        $this->table(
            ['State', 'Count'],
            collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        if ($details !== []) {
            $this->newLine();
            $this->warn('Provider catalog risks (read-only):');
            $this->table(
                ['Kind', 'Row ID', 'Provider', 'Remote ID', 'Risk'],
                array_map(fn (array $r) => [$r['kind'], $r['id'], $r['provider'], $r['remote_id'], $r['risk']], $details)
            );
        }

        $problemKeys = [
            'empty_remote_id',
            'provider_missing',
            'negative_price',
            'duplicate_provider_remote_id',
            'smm_min_over_max',
        ];

        $hasProblems = collect($problemKeys)->contains(fn ($key) => $state[$key] > 0);

        return $hasProblems ? self::FAILURE : self::SUCCESS;
    }
}
