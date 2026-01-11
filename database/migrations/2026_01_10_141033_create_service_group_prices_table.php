<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_group_prices', function (Blueprint $table) {
            $table->id();

            // service_id مرتبط بأي خدمة (imei_services / server_services / file_services)
            $table->unsignedBigInteger('service_id');

            // نوع الخدمة (imei/server/file) عشان نفس الجدول يخدم الكل
            $table->string('service_type')->default('imei');

            // group_id (ServiceGroup)
            $table->unsignedBigInteger('group_id');

            // السعر الخاص بالجروب
            $table->decimal('price', 12, 4)->default(0);

            // خصم (قيمة)
            $table->decimal('discount', 12, 4)->default(0);

            // نوع الخصم: 1 Credits / 2 Percent
            $table->tinyInteger('discount_type')->default(1);

            $table->timestamps();

            $table->index(['service_type', 'service_id']);
            $table->index('group_id');

            $table->unique(['service_type','service_id','group_id'], 'svc_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_group_prices');
    }
};
