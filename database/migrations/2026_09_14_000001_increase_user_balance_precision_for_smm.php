<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'balance')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('balance', 14, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'balance')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('balance', 12, 2)->default(0)->change();
        });
    }
};
