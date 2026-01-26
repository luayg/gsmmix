<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalize legacy remote_* tables:
     * - use api_provider_id only (drop api_provider_id)
     * - enforce UNIQUE(api_provider_id, remote_id)
     * - enforce FK(api_provider_id) -> api_providers(id) ON DELETE CASCADE
     */
    public function up(): void
    {
        $tables = [
            'remote_imei_services' => [
                'old_fk'     => 'ris_api_fk',
                'old_unique' => 'remote_imei_services_api_provider_id_remote_id_unique',
                'old_index'  => 'remote_imei_services_api_provider_id_index',
            ],
            'remote_file_services' => [
                'old_fk'     => 'rfs_api_fk',
                'old_unique' => 'remote_file_services_api_provider_id_remote_id_unique',
                'old_index'  => 'remote_file_services_api_provider_id_index',
            ],
            'remote_server_services' => [
                'old_fk'     => 'rss_api_fk',
                'old_unique' => 'remote_server_services_api_provider_id_remote_id_unique',
                'old_index'  => 'remote_server_services_api_provider_id_index',
            ],
        ];

        foreach ($tables as $table => $meta) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            // 1) Backfill api_provider_id from legacy api_provider_id (many rows have api_provider_id=0).
            if (Schema::hasColumn($table, 'api_provider_id') && Schema::hasColumn($table, 'api_provider_id')) {
                DB::table($table)
                    ->where('api_provider_id', 0)
                    ->update(['api_provider_id' => DB::raw('api_provider_id')]);
            }

            // 2) Drop legacy FK/indexes on api_provider_id then drop the api_provider_id column.
            if (Schema::hasColumn($table, 'api_provider_id')) {
                Schema::table($table, function (Blueprint $t) use ($meta) {
                    // Keep it idempotent (project already has multiple migrations around these columns).
                    try { $t->dropForeign($meta['old_fk']); } catch (\Throwable $e) {}
                    try { $t->dropUnique($meta['old_unique']); } catch (\Throwable $e) {}
                    try { $t->dropIndex($meta['old_index']); } catch (\Throwable $e) {}
                });

                Schema::table($table, function (Blueprint $t) {
                    // Drop api_provider_id after dropping its constraints.
                    $t->dropColumn('api_provider_id');
                });
            }

            // 3) Ensure UNIQUE(api_provider_id, remote_id)
            $uniqueName = "{$table}_api_provider_id_remote_id_unique";
            Schema::table($table, function (Blueprint $t) use ($uniqueName) {
                try { $t->unique(['api_provider_id', 'remote_id'], $uniqueName); } catch (\Throwable $e) {}
            });

            // 4) Ensure FK(api_provider_id) -> api_providers(id) ON DELETE CASCADE
            $fkName = "{$table}_api_provider_fk";
            Schema::table($table, function (Blueprint $t) use ($fkName) {
                try {
                    $t->foreign('api_provider_id', $fkName)
                        ->references('id')
                        ->on('api_providers')
                        ->cascadeOnDelete();
                } catch (\Throwable $e) {}
            });
        }
    }

    public function down(): void
    {
        // We keep down() conservative because bringing back api_provider_id is not needed for future work.
        // If you really need rollback, do it manually on a backup database.
    }
};
