<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('api_providers', function (Blueprint $table) {
            if (!Schema::hasColumn('api_providers', 'available_smm')) {
                $table->unsignedInteger('available_smm')->default(0)->after('used_file');
            }

            if (!Schema::hasColumn('api_providers', 'used_smm')) {
                $table->unsignedInteger('used_smm')->default(0)->after('available_smm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_providers', function (Blueprint $table) {
            if (Schema::hasColumn('api_providers', 'used_smm')) {
                $table->dropColumn('used_smm');
            }

            if (Schema::hasColumn('api_providers', 'available_smm')) {
                $table->dropColumn('available_smm');
            }
        });
    }
};