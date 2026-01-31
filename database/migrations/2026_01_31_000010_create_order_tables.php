<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // IMEI orders
        Schema::create('imei_orders', function (Blueprint $t) {
            $t->id();
            $t->string('device')->nullable();            // IMEI/SN/anything
            $t->string('remote_id')->nullable();         // provider reference id
            $t->string('status', 30)->default('waiting'); // waiting|inprogress|success|rejected|cancelled

            $t->decimal('order_price', 10, 2)->nullable();
            $t->decimal('price', 10, 2)->nullable();
            $t->decimal('profit', 10, 2)->nullable();

            $t->longText('request')->nullable();
            $t->longText('response')->nullable();
            $t->longText('comments')->nullable();

            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('email')->nullable();

            $t->unsignedBigInteger('service_id')->nullable();
            $t->unsignedBigInteger('supplier_id')->nullable();

            $t->boolean('needs_verify')->default(false);
            $t->boolean('expired')->default(false);
            $t->boolean('approved')->default(false);

            $t->string('ip', 64)->nullable();
            $t->boolean('api_order')->default(false);
            $t->json('params')->nullable();
            $t->boolean('processing')->default(false);
            $t->timestamp('replied_at')->nullable();

            $t->timestamps();

            $t->index(['status']);
            $t->index(['supplier_id']);
            $t->index(['service_id']);
            $t->index(['user_id']);
        });

        // Server orders
        Schema::create('server_orders', function (Blueprint $t) {
            $t->id();
            $t->string('device')->nullable();
            $t->integer('quantity')->nullable();
            $t->string('remote_id')->nullable();
            $t->string('status', 30)->default('waiting');

            $t->decimal('order_price', 10, 2)->nullable();
            $t->decimal('price', 10, 2)->nullable();
            $t->decimal('profit', 10, 2)->nullable();

            $t->longText('request')->nullable();
            $t->longText('response')->nullable();
            $t->longText('comments')->nullable();

            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('email')->nullable();

            $t->unsignedBigInteger('service_id')->nullable();
            $t->unsignedBigInteger('supplier_id')->nullable();

            $t->boolean('needs_verify')->default(false);
            $t->boolean('expired')->default(false);
            $t->boolean('approved')->default(false);

            $t->string('ip', 64)->nullable();
            $t->boolean('api_order')->default(false);
            $t->json('params')->nullable();
            $t->boolean('processing')->default(false);
            $t->timestamp('replied_at')->nullable();

            $t->timestamps();

            $t->index(['status']);
            $t->index(['supplier_id']);
            $t->index(['service_id']);
            $t->index(['user_id']);
        });

        // File orders
        Schema::create('file_orders', function (Blueprint $t) {
            $t->id();
            $t->string('device')->nullable();
            $t->string('storage_path')->nullable();
            $t->string('remote_id')->nullable();
            $t->string('status', 30)->default('waiting');

            $t->decimal('order_price', 10, 2)->nullable();
            $t->decimal('price', 10, 2)->nullable();
            $t->decimal('profit', 10, 2)->nullable();

            $t->longText('request')->nullable();
            $t->longText('response')->nullable();
            $t->longText('comments')->nullable();

            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('email')->nullable();

            $t->unsignedBigInteger('service_id')->nullable();
            $t->unsignedBigInteger('supplier_id')->nullable();

            $t->boolean('needs_verify')->default(false);
            $t->boolean('expired')->default(false);
            $t->boolean('approved')->default(false);

            $t->string('ip', 64)->nullable();
            $t->boolean('api_order')->default(false);
            $t->json('params')->nullable();
            $t->boolean('processing')->default(false);
            $t->timestamp('replied_at')->nullable();

            $t->timestamps();

            $t->index(['status']);
            $t->index(['supplier_id']);
            $t->index(['service_id']);
            $t->index(['user_id']);
        });

        // Product orders (placeholder standard)
        Schema::create('product_orders', function (Blueprint $t) {
            $t->id();
            $t->string('status', 30)->default('waiting');
            $t->decimal('order_price', 10, 2)->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('email')->nullable();
            $t->longText('comments')->nullable();
            $t->timestamps();

            $t->index(['status']);
            $t->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_orders');
        Schema::dropIfExists('file_orders');
        Schema::dropIfExists('server_orders');
        Schema::dropIfExists('imei_orders');
    }
};
