<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // IMEI: أضف min_qty / max_qty
        Schema::table('remote_imei_services', function (Blueprint $t) {
            if (!Schema::hasColumn('remote_imei_services', 'min_qty')) {
                $t->string('min_qty')->nullable()->after('info');
            }
            if (!Schema::hasColumn('remote_imei_services', 'max_qty')) {
                $t->string('max_qty')->nullable()->after('min_qty');
            }
        });

        // FILE: أضف min_qty / max_qty (احتياطيًا لأن سكريبت السينك عام)
        Schema::table('remote_file_services', function (Blueprint $t) {
            if (!Schema::hasColumn('remote_file_services', 'min_qty')) {
                $t->string('min_qty')->nullable()->after('info');
            }
            if (!Schema::hasColumn('remote_file_services', 'max_qty')) {
                $t->string('max_qty')->nullable()->after('min_qty');
            }
        });
    }

    public function down(): void
    {
        // ملاحظة: إسقاط الأعمدة قد يتطلب doctrine/dbal في بعض البيئات
        Schema::table('remote_imei_services', function (Blueprint $t) {
            if (Schema::hasColumn('remote_imei_services', 'min_qty')) {
                $t->dropColumn(['min_qty','max_qty']);
            }
        });

        Schema::table('remote_file_services', function (Blueprint $t) {
            if (Schema::hasColumn('remote_file_services', 'min_qty')) {
                $t->dropColumn(['min_qty','max_qty']);
            }
        });
    }
};
