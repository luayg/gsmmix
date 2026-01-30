<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email')->nullable()->index();

            $table->string('status')->default('new')->index();
            $table->decimal('total', 18, 4)->default(0);
            $table->text('notes')->nullable();

            $table->json('items')->nullable(); // لاحقاً: منتجات/كميات/أسعار
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_orders');
    }
};
