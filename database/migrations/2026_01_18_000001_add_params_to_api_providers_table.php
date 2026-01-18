<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('api_providers', function (Blueprint $table) {
            // يشبه نظام الصور عندك: تخزين إعدادات كل مزود بصيغة JSON
            // مثال: {"main_field":"imei","method":"post"}
            if (!Schema::hasColumn('api_providers', 'params')) {
                $table->longText('params')->nullable()->after('api_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_providers', function (Blueprint $table) {
            if (Schema::hasColumn('api_providers', 'params')) {
                $table->dropColumn('params');
            }
        });
    }
};
