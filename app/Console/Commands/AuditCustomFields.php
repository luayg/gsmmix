<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditCustomFields extends Command
{
    protected $signature = 'services:custom-field-audit {--details=50 : Maximum risky rows to display}';

    protected $description = 'Read-only audit for custom-field service references and structural validity.';

    public function handle(): int
    {
        if (!Schema::hasTable('custom_fields')) {
            $this->error('custom_fields table is missing.');
            return self::FAILURE;
        }

        $maps = [
            'imei_service' => 'imei_services',
            'server_service' => 'server_services',
            'file_service' => 'file_services',
            'smm_service' => 'smm_services',
        ];

        $state = [
            'rows_scanned' => 0,
            'invalid_service_type' => 0,
            'missing_service' => 0,
            'empty_input_name' => 0,
            'minimum_over_maximum' => 0,
            'duplicate_input_per_service' => 0,
        ];

        $limit = max(0, min(200, (int)$this->option('details')));
        $details = [];

        $serviceIds = [];
        foreach ($maps as $type => $table) {
            $serviceIds[$type] = Schema::hasTable($table)
                ? DB::table($table)->pluck('id')->map(fn ($id) => (int)$id)->flip()->all()
                : [];
        }

        $rows = DB::table('custom_fields')
            ->orderBy('id')
            ->get(['id','service_id','service_type','input','minimum','maximum','active','required','ordering']);

        $state['rows_scanned'] = $rows->count();
        $seenInputs = [];

        foreach ($rows as $row) {
            $type = strtolower(trim((string)$row->service_type));
            $serviceId = (int)$row->service_id;
            $input = strtolower(trim((string)$row->input));
            $minimum = (int)$row->minimum;
            $maximum = (int)$row->maximum;
            $risks = [];

            if (!array_key_exists($type, $maps)) {
                $state['invalid_service_type']++;
                $risks[] = 'invalid_service_type';
            } elseif (!isset($serviceIds[$type][$serviceId])) {
                $state['missing_service']++;
                $risks[] = 'missing_service';
            }

            if ($input === '') {
                $state['empty_input_name']++;
                $risks[] = 'empty_input_name';
            } else {
                $key = $type . ':' . $serviceId . ':' . $input;
                if (isset($seenInputs[$key])) {
                    $state['duplicate_input_per_service']++;
                    $risks[] = 'duplicate_input_per_service';
                } else {
                    $seenInputs[$key] = (int)$row->id;
                }
            }

            if ($minimum > 0 && $maximum > 0 && $minimum > $maximum) {
                $state['minimum_over_maximum']++;
                $risks[] = 'minimum_over_maximum';
            }

            if ($risks !== [] && count($details) < $limit) {
                $details[] = [
                    'id' => (int)$row->id,
                    'type' => $type !== '' ? $type : '—',
                    'service_id' => $serviceId,
                    'input' => (string)($row->input ?? ''),
                    'min' => $minimum,
                    'max' => $maximum,
                    'risk' => implode(',', $risks),
                ];
            }
        }

        $this->info('Read-only custom field audit');
        $this->table(
            ['State', 'Count'],
            collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        if ($details !== []) {
            $this->newLine();
            $this->warn('Custom field risks (read-only):');
            $this->table(
                ['ID','Type','Service','Input','Min','Max','Risk'],
                array_map(fn (array $r) => [
                    $r['id'],$r['type'],$r['service_id'],$r['input'],$r['min'],$r['max'],$r['risk'],
                ], $details)
            );
        }

        $problemKeys = [
            'invalid_service_type',
            'missing_service',
            'empty_input_name',
            'minimum_over_maximum',
            'duplicate_input_per_service',
        ];

        return collect($problemKeys)->contains(fn ($key) => $state[$key] > 0)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
