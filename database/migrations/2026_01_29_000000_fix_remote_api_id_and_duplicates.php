<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    private function indexExists(string $table, string $indexName): bool
    {
        $row = DB::selectOne(
            "SELECT 1
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?
             LIMIT 1",
            [$table, $indexName]
        );
        return (bool) $row;
    }

    public function up(): void
    {
        $tables = [
            'remote_imei_services',
            'remote_server_services',
            'remote_file_services',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) continue;

            // 1) Ensure api_provider_id exists
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'api_provider_id')) {
                    $t->unsignedBigInteger('api_provider_id')->nullable()->after('id');
                }
            });

            // 2) Backfill api_provider_id from legacy api_id (if exists)
            $hasApiId = Schema::hasColumn($table, 'api_id');
            if ($hasApiId) {
                // املأ api_provider_id إذا كان null/0
                DB::statement("UPDATE `$table`
                               SET api_provider_id = api_id
                               WHERE (api_provider_id IS NULL OR api_provider_id = 0)
                                 AND api_id IS NOT NULL");
            }

            // 3) Drop legacy unique/index safely (IF EXISTS)
            $legacyUnique = $table . '_api_id_remote_id_unique'; // غالباً هذا كان موجود بالقديم
            if ($this->indexExists($table, $legacyUnique)) {
                DB::statement("ALTER TABLE `$table` DROP INDEX `$legacyUnique`");
            }

            // في بعض قواعد البيانات اتسمت بشكل مختلف (احتياط)
            $legacyUnique2 = $table . '_api_provider_id_remote_id_unique';
            // لا نسقطه هنا إلا لو كان "قديم" ومحتاج إعادة بناء (عشان لا نفشل)
            // سنقوم لاحقاً بإنشاءه إذا غير موجود.

            // 4) Drop api_id column (the real fix for your error)
            Schema::table($table, function (Blueprint $t) use ($table, $hasApiId) {
                if ($hasApiId) {
                    // نجعلها nullable أولاً لتفادي مشاكل بعض المحركات أثناء drop
                    try { $t->unsignedBigInteger('api_id')->nullable()->change(); } catch (\Throwable $e) {}
                }
            });

            Schema::table($table, function (Blueprint $t) use ($table, $hasApiId) {
                if ($hasApiId && Schema::hasColumn($table, 'api_id')) {
                    $t->dropColumn('api_id');
                }
            });

            // 5) Ensure unique(api_provider_id, remote_id) exists
            if (!$this->indexExists($table, $legacyUnique2)) {
                Schema::table($table, function (Blueprint $t) use ($legacyUnique2) {
                    $t->unique(['api_provider_id', 'remote_id'], $legacyUnique2);
                });
            }

            // 6) Ensure index(api_provider_id) exists (optional but useful)
            $idx = $table . '_api_provider_id_index';
            if (!$this->indexExists($table, $idx)) {
                Schema::table($table, function (Blueprint $t) use ($idx) {
                    $t->index('api_provider_id', $idx);
                });
            }
        }
    }

    public function down(): void
    {
        // intentionally empty (this is a normalization migration)
    }
};
