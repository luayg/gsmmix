<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        // ✅ لا تضف العمود إذا كان موجود (حل مشكلة Duplicate column)
        if (!Schema::hasColumn('remote_imei_services', 'api_provider_id')) {
            Schema::table('remote_imei_services', function (Blueprint $table) {
                $table->unsignedBigInteger('api_provider_id')->nullable()->index();
            });
        }

        if (!Schema::hasColumn('remote_server_services', 'api_provider_id')) {
            Schema::table('remote_server_services', function (Blueprint $table) {
                $table->unsignedBigInteger('api_provider_id')->nullable()->index();
            });
        }

        if (!Schema::hasColumn('remote_file_services', 'api_provider_id')) {
            Schema::table('remote_file_services', function (Blueprint $table) {
                $table->unsignedBigInteger('api_provider_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // ✅ لا تحاول حذف عمود غير موجود
        if (Schema::hasColumn('remote_imei_services', 'api_provider_id')) {
            Schema::table('remote_imei_services', function (Blueprint $table) {
                $table->dropColumn('api_provider_id');
            });
        }

        if (Schema::hasColumn('remote_server_services', 'api_provider_id')) {
            Schema::table('remote_server_services', function (Blueprint $table) {
                $table->dropColumn('api_provider_id');
            });
        }

        if (Schema::hasColumn('remote_file_services', 'api_provider_id')) {
            Schema::table('remote_file_services', function (Blueprint $table) {
                $table->dropColumn('api_provider_id');
            });
        }
    }
};
