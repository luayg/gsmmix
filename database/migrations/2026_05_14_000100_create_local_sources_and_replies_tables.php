<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('local_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_source_id')->nullable()->constrained('local_sources')->nullOnDelete();
            $table->boolean('device_based')->default(false);
            $table->string('device_identifier')->nullable();
            $table->longText('reply');
            $table->foreignId('used_by_product_order_id')->nullable()->constrained('product_orders')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['local_source_id', 'device_based']);
            $table->index(['used_by_product_order_id']);
            $table->index(['expires_at']);
            $table->index(['device_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_replies');
        Schema::dropIfExists('local_sources');
    }
};