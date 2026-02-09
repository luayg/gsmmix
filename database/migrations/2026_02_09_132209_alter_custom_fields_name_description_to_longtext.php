<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            // ⚠️ requires doctrine/dbal
            $table->longText('name')->change();
            $table->longText('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            // rollback to previous state
            $table->string('name', 255)->change();
            $table->string('description', 255)->nullable()->change();
        });
    }
};
