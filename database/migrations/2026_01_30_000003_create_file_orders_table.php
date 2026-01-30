<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('file_orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email')->nullable()->index();

            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();

            $table->string('device')->nullable(); // نص/معلومة مرتبطة بالطلب
            $table->string('storage_path')->nullable(); // مسار الملف لو رفعت ملف
            $table->string('remote_id')->nullable()->index();

            $table->string('status')->default('new')->index();
            $table->decimal('order_price', 18, 4)->default(0);
            $table->decimal('price', 18, 4)->default(0);
            $table->decimal('profit', 18, 4)->default(0);

            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->text('comments')->nullable();

            $table->boolean('needs_verify')->default(false)->index();
            $table->boolean('expired')->default(false)->index();
            $table->boolean('approved')->default(false)->index();
            $table->string('ip')->nullable();

            $table->boolean('api_order')->default(false)->index();
            $table->json('params')->nullable();

            $table->boolean('processing')->default(false)->index();
            $table->timestamp('replied_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_orders');
    }
};
