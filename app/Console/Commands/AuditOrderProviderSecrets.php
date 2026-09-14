<?php

namespace App\Console\Commands;

use App\Services\Orders\ProviderPayloadSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AuditOrderProviderSecrets extends Command
{
    protected $signature = 'orders:provider-secret-audit {--json : Print counts as JSON}';
    protected $description = 'Read-only audit for provider credentials accidentally stored inside order metadata.';

    public function handle(ProviderPayloadSanitizer $sanitizer): int
    {
        $counts = [
            'tables_scanned' => 0,
            'orders_scanned' => 0,
            'orders_with_provider_secret_metadata' => 0,
        ];

        foreach (['imei_orders', 'server_orders', 'file_orders', 'smm_orders'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'request')) {
                continue;
            }

            $counts['tables_scanned']++;

            DB::table($table)->select(['id', 'request'])->orderBy('id')
                ->chunkById(200, function ($rows) use (&$counts, $sanitizer): void {
                    foreach ($rows as $row) {
                        $counts['orders_scanned']++;

                        if (!is_string($row->request) || trim($row->request) === '') {
                            continue;
                        }

                        $request = json_decode($row->request, true);
                        if (!is_array($request)) {
                            continue;
                        }

                        $clean = $request;
                        if (array_key_exists('request', $clean)) {
                            $clean['request'] = $sanitizer->sanitizeRequest($clean['request']);
                        }
                        if (array_key_exists('response_raw', $clean)) {
                            $clean['response_raw'] = $sanitizer->sanitizeResponse($clean['response_raw']);
                        }
                        if (array_key_exists('status_check_raw', $clean)) {
                            $clean['status_check_raw'] = $sanitizer->sanitizeResponse($clean['status_check_raw']);
                        }

                        if ($clean !== $request) {
                            $counts['orders_with_provider_secret_metadata']++;
                        }
                    }
                });
        }

        if ($this->option('json')) {
            $this->line(json_encode($counts));
        } else {
            $this->info('Read-only order provider secret audit');
            $this->table(['State', 'Count'], collect($counts)->map(fn ($v, $k) => [$k, $v])->values()->all());
        }

        return $counts['orders_with_provider_secret_metadata'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
