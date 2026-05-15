<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('ordering')->default(0);
            $table->timestamps();

            $table->unique('name');
            $table->index(['active', 'ordering']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('local_source_id')->nullable()->constrained('local_sources')->nullOnDelete();
            $table->string('name');
            $table->string('alias')->nullable();
            $table->longText('description')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('device_based')->default(false);
            $table->unsignedInteger('ordering')->default(0);
            $table->timestamps();

            $table->unique('alias');
            $table->index(['product_category_id', 'active']);
            $table->index(['local_source_id']);
            $table->index(['device_based']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
