<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('remote_imei_services', function (Blueprint $table) {
            $table->unsignedBigInteger('api_provider_id')->index();
        });

        Schema::table('remote_server_services', function (Blueprint $table) {
            $table->unsignedBigInteger('api_provider_id')->index();
        });

        Schema::table('remote_file_services', function (Blueprint $table) {
            $table->unsignedBigInteger('api_provider_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('remote_imei_services', function (Blueprint $table) {
            $table->dropColumn('api_provider_id');
        });

        Schema::table('remote_server_services', function (Blueprint $table) {
            $table->dropColumn('api_provider_id');
        });

        Schema::table('remote_file_services', function (Blueprint $table) {
            $table->dropColumn('api_provider_id');
        });
    }
};
