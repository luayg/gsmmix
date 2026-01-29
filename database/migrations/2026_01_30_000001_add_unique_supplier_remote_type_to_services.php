<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function dbName(): string
    {
        return DB::getDatabaseName();
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $row = DB::selectOne(
            "SELECT 1
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
             LIMIT 1",
            [$this->dbName(), $table, $indexName]
        );

        return (bool) $row;
    }

    public function up(): void
    {
        $tables = [
            'server_services',
            'imei_services',
            'file_services',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) continue;

            // لازم تكون الأعمدة موجودة
            if (!Schema::hasColumn($table, 'supplier_id') || !Schema::hasColumn($table, 'remote_id')) {
                continue;
            }

            // بعض الجداول فيها type (كما في migrations) :contentReference[oaicite:3]{index=3}
            // سنستخدم type إن وجد، وإلا نعمل unique على supplier_id + remote_id فقط
            $hasType = Schema::hasColumn($table, 'type');

            $uniqName = $hasType
                ? "{$table}_supplier_id_remote_id_type_unique"
                : "{$table}_supplier_id_remote_id_unique";

            if ($this->indexExists($table, $uniqName)) {
                continue;
            }

            // ✅ قبل إضافة unique: احذف التكرارات (يُبقي أصغر id)
            if ($hasType) {
                DB::statement("
                    DELETE t1 FROM `$table` t1
                    JOIN `$table` t2
                      ON t1.supplier_id <=> t2.supplier_id
                     AND t1.remote_id   <=> t2.remote_id
                     AND t1.type        <=> t2.type
                     AND t1.id > t2.id
                    WHERE t1.supplier_id IS NOT NULL
                      AND t1.remote_id IS NOT NULL
                ");
            } else {
                DB::statement("
                    DELETE t1 FROM `$table` t1
                    JOIN `$table` t2
                      ON t1.supplier_id <=> t2.supplier_id
                     AND t1.remote_id   <=> t2.remote_id
                     AND t1.id > t2.id
                    WHERE t1.supplier_id IS NOT NULL
                      AND t1.remote_id IS NOT NULL
                ");
            }

            Schema::table($table, function (Blueprint $t) use ($hasType, $uniqName) {
                if ($hasType) {
                    $t->unique(['supplier_id', 'remote_id', 'type'], $uniqName);
                } else {
                    $t->unique(['supplier_id', 'remote_id'], $uniqName);
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'server_services',
            'imei_services',
            'file_services',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) continue;

            $hasType = Schema::hasColumn($table, 'type');
            $uniqName = $hasType
                ? "{$table}_supplier_id_remote_id_type_unique"
                : "{$table}_supplier_id_remote_id_unique";

            if (!$this->indexExists($table, $uniqName)) continue;

            Schema::table($table, function (Blueprint $t) use ($hasType, $uniqName) {
                if ($hasType) {
                    $t->dropUnique($uniqName);
                } else {
                    $t->dropUnique($uniqName);
                }
            });
        }
    }
};
