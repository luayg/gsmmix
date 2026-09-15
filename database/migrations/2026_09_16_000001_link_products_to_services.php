<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'service_type')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->string('source_type', 20)->default('manual')->after('local_source_id');
                $table->string('service_type', 20)->nullable()->after('source_type');
                $table->unsignedBigInteger('service_id')->nullable()->after('service_type');
                $table->index(['service_type', 'service_id'], 'products_service_reference_index');
            });
        } elseif (!Schema::hasColumn('products', 'source_type')) {
            Schema::table('products', fn (Blueprint $table) => $table->string('source_type', 20)->default('manual')->after('local_source_id'));
        }

        DB::table('products')->whereNotNull('local_source_id')->where('source_type', 'manual')
            ->update(['source_type' => 'local_source']);

        if (!Schema::hasColumn('product_orders', 'service_order_type')) {
            Schema::table('product_orders', function (Blueprint $table): void {
                $table->string('service_order_type', 20)->nullable()->after('product_id');
                $table->unsignedBigInteger('service_order_id')->nullable()->after('service_order_type');
                $table->index(['service_order_type', 'service_order_id'], 'product_orders_service_order_index');
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Product and generated service-order history must be preserved. Use a reviewed forward migration.');
    }
};
