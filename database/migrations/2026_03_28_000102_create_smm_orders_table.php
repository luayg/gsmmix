<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smm_orders', function (Blueprint $table) {
            $table->id();

            $table->string('device')->nullable();
            $table->unsignedInteger('quantity')->default(1);

            $table->string('remote_id')->nullable();
            $table->string('status')->default('waiting');

            $table->decimal('order_price', 12, 4)->default(0);
            $table->decimal('price', 12, 4)->default(0);
            $table->decimal('profit', 12, 4)->default(0);

            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->text('comments')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email')->nullable();

            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();

            $table->boolean('needs_verify')->default(false);
            $table->boolean('expired')->default(false);
            $table->boolean('approved')->default(false);

            $table->string('ip', 64)->nullable();
            $table->boolean('api_order')->default(false);

            $table->json('params')->nullable();
            $table->boolean('processing')->default(false);
            $table->timestamp('replied_at')->nullable();

            $table->timestamps();

            $table->index(['status']);
            $table->index(['remote_id']);
            $table->index(['user_id']);
            $table->index(['service_id']);
            $table->index(['supplier_id']);
            $table->index(['api_order']);
            $table->index(['processing']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smm_orders');
    }
};