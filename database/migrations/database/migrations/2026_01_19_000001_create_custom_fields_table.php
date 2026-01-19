<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('service_id')->index();
            // مثال: imei_service | server_service | file_service
            $table->string('service_type', 50)->index();

            $table->string('name', 255);          // الظاهر للمستخدم (Name)
            $table->string('input', 255)->nullable(); // اسم الحقل الذي يُرسل للـ API (Input name)
            $table->string('field_type', 50)->default('text'); // text,password,dropdown,...
            $table->longText('field_options')->nullable();     // dropdown/radio options

            $table->string('description', 255)->nullable();

            // validation rules (numeric, alphanumeric, email, ...)
            $table->string('validation', 50)->nullable();

            $table->unsignedInteger('minimum')->default(0);
            $table->unsignedInteger('maximum')->default(0);

            $table->boolean('required')->default(false);
            $table->boolean('active')->default(true);

            $table->unsignedInteger('ordering')->default(0);

            $table->timestamps();

            $table->index(['service_id', 'service_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
