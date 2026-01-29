<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

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

        return (bool)$row;
    }

    private function foreignKeyNamesReferencing(string $table, string $refTable): array
    {
        $rows = DB::select(
            "SELECT DISTINCT constraint_name AS name
             FROM information_schema.key_column_usage
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND referenced_table_name = ?",
            [$table, $refTable]
        );

        return array_values(array_filter(array_map(fn($r) => $r->name ?? null, $rows)));
    }

    private function dropForeignKeyIfExists(string $table, string $fkName): void
    {
        // MySQL: DROP FOREIGN KEY يفشل لو الاسم غير موجود -> نتحقق أولاً
        $rows = DB::select(
            "SELECT 1
             FROM information_schema.table_constraints
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND constraint_name = ?
               AND constraint_type = 'FOREIGN KEY'
             LIMIT 1",
            [$table, $fkName]
        );

        if ($rows) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        }
    }

    private function ensureApiProviderId(string $table): void
    {
        // 1) لو api_provider_id غير موجود و api_id موجود -> CHANGE (rename + type fix)
        if (!$this->columnExists($table, 'api_provider_id') && $this->columnExists($table, 'api_id')) {
            DB::statement("ALTER TABLE `{$table}` CHANGE `api_id` `api_provider_id` BIGINT UNSIGNED NULL");
            return;
        }

        // 2) لو api_provider_id غير موجود نهائياً -> ADD
        if (!$this->columnExists($table, 'api_provider_id')) {
            DB::statement("ALTER TABLE `{$table}` ADD `api_provider_id` BIGINT UNSIGNED NULL AFTER `id`");
            return;
        }

        // 3) لو موجود -> تأكيد النوع nullable + unsigned big int
        DB::statement("ALTER TABLE `{$table}` MODIFY `api_provider_id` BIGINT UNSIGNED NULL");
    }

    private function backfillAndDropApiIdIfNeeded(string $table): void
    {
        if ($this->columnExists($table, 'api_id') && $this->columnExists($table, 'api_provider_id')) {
            // نقل البيانات
            DB::statement("UPDATE `{$table}` SET `api_provider_id` = `api_id` WHERE `api_provider_id` IS NULL");
            // حذف العمود القديم
            DB::statement("ALTER TABLE `{$table}` DROP COLUMN `api_id`");
        }
    }

    private function dedupeByProviderAndRemote(string $table): void
    {
        // احذف التكرارات (أبقِ أقل id)
        // ملاحظة: COALESCE لأن api_provider_id قد يكون NULL
        DB::statement("
            DELETE t1
            FROM `{$table}` t1
            INNER JOIN `{$table}` t2
              ON t1.id > t2.id
             AND COALESCE(t1.api_provider_id, 0) = COALESCE(t2.api_provider_id, 0)
             AND t1.remote_id = t2.remote_id
        ");
    }

    private function ensureUniqueAndFk(string $table, string $fkName, string $uniqName): void
    {
        // إزالة أي FK قديم يشير لـ api_providers (بأي اسم)
        foreach ($this->foreignKeyNamesReferencing($table, 'api_providers') as $name) {
            $this->dropForeignKeyIfExists($table, $name);
        }

        // إزالة أسماء متوقعة قديمة (لو كانت موجودة)
        $this->dropForeignKeyIfExists($table, 'ris_api_fk');
        $this->dropForeignKeyIfExists($table, 'ris_api_provider_fk');
        $this->dropForeignKeyIfExists($table, $fkName);

        // إزالة unique قديم محتمل
        $this->dropIndexIfExists($table, "{$table}_api_id_remote_id_unique");
        $this->dropIndexIfExists($table, "{$table}_api_provider_id_remote_id_unique");
        $this->dropIndexIfExists($table, $uniqName);

        // إزالة التكرارات قبل إضافة unique
        $this->dedupeByProviderAndRemote($table);

        // إضافة unique
        DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$uniqName}` (`api_provider_id`, `remote_id`)");

        // إضافة FK صحيح
        DB::statement("
            ALTER TABLE `{$table}`
            ADD CONSTRAINT `{$fkName}`
            FOREIGN KEY (`api_provider_id`) REFERENCES `api_providers`(`id`)
            ON DELETE SET NULL
        ");
    }

    public function up(): void
    {
        // الجداول التي فيها خدمات remote
        $tables = [
            'remote_imei_services',
            'remote_server_services',
            'remote_file_services',
        ];

        foreach ($tables as $table) {
            if (!$this->tableExists($table)) continue;

            // تأكد من وجود api_provider_id بالشكل الصحيح
            $this->ensureApiProviderId($table);

            // لو كان api_id موجود مع api_provider_id -> انقل ثم احذف
            $this->backfillAndDropApiIdIfNeeded($table);

            // أسماء قياسية
            $fk   = match ($table) {
                'remote_imei_services'   => 'ris_api_provider_fk',
                'remote_server_services' => 'rss_api_provider_fk',
                'remote_file_services'   => 'rfs_api_provider_fk',
                default => "{$table}_api_provider_fk",
            };

            $uniq = "{$table}_api_provider_id_remote_id_unique";

            $this->ensureUniqueAndFk($table, $fk, $uniq);
        }
    }

    public function down(): void
    {
        // لا نرجّع api_id مرة أخرى. فقط نحذف الـFK/Unique إن رغبت.
        $tables = [
            'remote_imei_services',
            'remote_server_services',
            'remote_file_services',
        ];

        foreach ($tables as $table) {
            if (!$this->tableExists($table)) continue;

            // drop FK names (لو موجودة)
            foreach ($this->foreignKeyNamesReferencing($table, 'api_providers') as $name) {
                $this->dropForeignKeyIfExists($table, $name);
            }

            // drop unique
            $this->dropIndexIfExists($table, "{$table}_api_provider_id_remote_id_unique");
        }
    }
};
