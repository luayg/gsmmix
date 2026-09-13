<?php

use App\Services\Orders\ProviderPayloadSanitizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sanitizer = app(ProviderPayloadSanitizer::class);

        foreach (['imei_orders', 'server_orders', 'file_orders', 'smm_orders'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'request')) {
                continue;
            }

            DB::table($table)->select(['id', 'request'])->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $sanitizer): void {
                    foreach ($rows as $row) {
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

                        if ($clean === $request) {
                            continue;
                        }

                        DB::table($table)->where('id', $row->id)->update([
                            'request' => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        throw new LogicException('Redacted provider credentials cannot be restored by rollback. Restore a verified backup only if absolutely necessary.');
    }
};
