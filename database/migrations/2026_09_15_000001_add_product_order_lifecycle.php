<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historical orders get no automatic charge or billing metadata.
        if (!Schema::hasColumn('product_orders', 'request_uid')) {
            Schema::table('product_orders', fn (Blueprint $table) => $table->uuid('request_uid')->nullable()->unique());
        }
        if (!Schema::hasColumn('product_orders', 'device')) {
            Schema::table('product_orders', fn (Blueprint $table) => $table->string('device')->nullable());
        }
        foreach (['request', 'response'] as $column) {
            if (!Schema::hasColumn('product_orders', $column)) {
                Schema::table('product_orders', fn (Blueprint $table) => $table->longText($column)->nullable());
            }
        }
        if (!Schema::hasColumn('product_orders', 'replied_at')) {
            Schema::table('product_orders', fn (Blueprint $table) => $table->timestamp('replied_at')->nullable());
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Product order billing and delivery history must be preserved. Use a reviewed forward migration.');
    }
};
