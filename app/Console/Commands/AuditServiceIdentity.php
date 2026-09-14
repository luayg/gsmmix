<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditServiceIdentity extends Command
{
    protected $signature = 'services:identity-audit {--details=50 : Maximum risky rows to display}';

    protected $description = 'Read-only audit of local service aliases, source values, and provider/remote identity mappings.';

    public function handle(): int
    {
        $tables = [
            'imei' => 'imei_services',
            'server' => 'server_services',
            'file' => 'file_services',
            'smm' => 'smm_services',
        ];

        $state = [
            'service_tables_scanned' => 0,
            'services_scanned' => 0,
            'empty_alias' => 0,
            'duplicate_alias' => 0,
            'invalid_source' => 0,
            'duplicate_provider_remote_mapping' => 0,
        ];

        $details = [];
        $limit = max(0, min(200, (int)$this->option('details')));

        foreach ($tables as $kind => $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $state['service_tables_scanned']++;
            $columns = Schema::getColumnListing($table);
            $select = array_values(array_intersect([
                'id', 'alias', 'source', 'supplier_id', 'remote_id',
            ], $columns));

            if (!in_array('id', $select, true)) {
                continue;
            }

            $rows = DB::table($table)->orderBy('id')->get($select);
            $state['services_scanned'] += $rows->count();

            $seenAliases = [];
            $seenRemoteMappings = [];

            foreach ($rows as $row) {
                $risks = [];
                $alias = in_array('alias', $columns, true)
                    ? trim((string)($row->alias ?? ''))
                    : '';

                if (in_array('alias', $columns, true)) {
                    if ($alias === '') {
                        $state['empty_alias']++;
                        $risks[] = 'empty_alias';
                    } else {
                        $aliasKey = mb_strtolower($alias);
                        if (isset($seenAliases[$aliasKey])) {
                            $state['duplicate_alias']++;
                            $risks[] = 'duplicate_alias';
                        } else {
                            $seenAliases[$aliasKey] = (int)$row->id;
                        }
                    }
                }

                if (in_array('source', $columns, true) && $row->source !== null && $row->source !== '') {
                    $source = (int)$row->source;
                    if (!in_array($source, [1, 2], true)) {
                        $state['invalid_source']++;
                        $risks[] = 'invalid_source';
                    }
                }

                $supplierId = in_array('supplier_id', $columns, true)
                    ? (int)($row->supplier_id ?? 0)
                    : 0;
                $remoteId = in_array('remote_id', $columns, true)
                    ? trim((string)($row->remote_id ?? ''))
                    : '';

                if ($supplierId > 0 && $remoteId !== '') {
                    $mappingKey = $supplierId . '|' . $remoteId;
                    if (isset($seenRemoteMappings[$mappingKey])) {
                        $state['duplicate_provider_remote_mapping']++;
                        $risks[] = 'duplicate_provider_remote_mapping';
                    } else {
                        $seenRemoteMappings[$mappingKey] = (int)$row->id;
                    }
                }

                if ($risks !== [] && count($details) < $limit) {
                    $details[] = [
                        'kind' => $kind,
                        'id' => (int)$row->id,
                        'alias' => $alias !== '' ? $alias : '—',
                        'provider' => $supplierId > 0 ? $supplierId : '—',
                        'remote_id' => $remoteId !== '' ? $remoteId : '—',
                        'risk' => implode(',', $risks),
                    ];
                }
            }
        }

        $this->info('Read-only local service identity audit');
        $this->table(
            ['State', 'Count'],
            collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        if ($details !== []) {
            $this->newLine();
            $this->warn('Service identity risks (read-only):');
            $this->table(
                ['Kind', 'Service', 'Alias', 'Provider', 'Remote ID', 'Risk'],
                array_map(fn (array $r) => [
                    $r['kind'], $r['id'], $r['alias'], $r['provider'], $r['remote_id'], $r['risk'],
                ], $details)
            );
        }

        $problemKeys = [
            'empty_alias',
            'duplicate_alias',
            'invalid_source',
            'duplicate_provider_remote_mapping',
        ];

        $hasProblems = collect($problemKeys)->contains(fn ($key) => $state[$key] > 0);

        return $hasProblems ? self::FAILURE : self::SUCCESS;
    }
}
