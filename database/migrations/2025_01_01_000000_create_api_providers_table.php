<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('api_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['dhru','webx','gsmhub','unlockbase','simple_link'])->index();
            $table->string('url');
            $table->string('username')->nullable();
            $table->string('api_key')->nullable();

            // خيارات المزامنة/الحالة
            $table->boolean('sync_imei')->default(true);
            $table->boolean('sync_server')->default(false);
            $table->boolean('sync_file')->default(false);
            $table->boolean('ignore_low_balance')->default(false);
            $table->boolean('auto_sync')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('synced')->default(false);

            // مؤشرات وملخصات
            $table->decimal('balance', 12, 2)->default(0);
            $table->unsignedInteger('available_imei')->default(0);
            $table->unsignedInteger('used_imei')->default(0);
            $table->unsignedInteger('available_server')->default(0);
            $table->unsignedInteger('used_server')->default(0);
            $table->unsignedInteger('available_file')->default(0);
            $table->unsignedInteger('used_file')->default(0);

            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('api_providers');
    }
};
