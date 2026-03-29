<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('api_providers', function (Blueprint $table) {
            if (!Schema::hasColumn('api_providers', 'sync_smm')) {
                $table->boolean('sync_smm')->default(true)->after('sync_file');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_providers', function (Blueprint $table) {
            if (Schema::hasColumn('api_providers', 'sync_smm')) {
                $table->dropColumn('sync_smm');
            }
        });
    }
};