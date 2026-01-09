<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // === IMEI ===
        Schema::create('remote_imei_services', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('api_id');              // مزوّدنا الداخلي
            $t->string('remote_id');                    // SERVICEID عند DHRU
            $t->string('name');                         // SERVICENAME
            $t->string('group_name')->nullable();       // اسم المجموعة
            $t->decimal('price', 12, 4)->default(0);    // CREDIT
            $t->string('time')->nullable();             // TIME
            $t->text('info')->nullable();               // INFO

            // أعلام المتطلبات (0/1)
            $t->boolean('network')->default(0);
            $t->boolean('mobile')->default(0);
            $t->boolean('provider')->default(0);
            $t->boolean('pin')->default(0);
            $t->boolean('kbh')->default(0);
            $t->boolean('mep')->default(0);
            $t->boolean('prd')->default(0);
            $t->boolean('type')->default(0);
            $t->boolean('locks')->default(0);
            $t->boolean('reference')->default(0);
            $t->boolean('udid')->default(0);
            $t->boolean('serial')->default(0);
            $t->boolean('secro')->default(0);

            // تسعيرة المجموعات (JSON كنخزّنوها نصّ)
            $t->longText('credit_groups')->nullable();

            // حقول إضافية
            $t->longText('additional_fields')->nullable(); // Requires.Custom / CustomFields
            $t->longText('additional_data')->nullable();   // أي بيانات إضافية
            $t->longText('params')->nullable();            // الخام إذا بغيتي
            $t->timestamps();

            $t->unique(['api_id','remote_id']);
            $t->index('api_id');
        });

        // === SERVER ===
        Schema::create('remote_server_services', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('api_id');
            $t->string('remote_id');
            $t->string('name');
            $t->string('group_name')->nullable();
            $t->decimal('price', 12, 4)->default(0);
            $t->string('time')->nullable();
            $t->text('info')->nullable();

            $t->string('min_qty')->nullable();
            $t->string('max_qty')->nullable();

            // بعض لوحات DHRU كترد مجموعات كريدي
            $t->longText('credit_groups')->nullable();

            $t->longText('additional_fields')->nullable();
            $t->longText('additional_data')->nullable();
            $t->longText('params')->nullable();
            $t->timestamps();

            $t->unique(['api_id','remote_id']);
            $t->index('api_id');
        });

        // === FILE ===
        Schema::create('remote_file_services', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('api_id');
            $t->string('remote_id');
            $t->string('name');
            $t->string('group_name')->nullable();
            $t->decimal('price', 12, 4)->default(0);
            $t->string('time')->nullable();
            $t->text('info')->nullable();

            $t->string('allowed_extensions')->nullable(); // ALLOW_EXTENSION

            // تناسقًا مع الباقي
            $t->longText('credit_groups')->nullable();

            $t->longText('additional_fields')->nullable();
            $t->longText('additional_data')->nullable();
            $t->longText('params')->nullable();
            $t->timestamps();

            $t->unique(['api_id','remote_id']);
            $t->index('api_id');
        });
    }

    public function down(): void
    {
        // إسقاط بالترتيب العكسي
        Schema::dropIfExists('remote_file_services');
        Schema::dropIfExists('remote_server_services');
        Schema::dropIfExists('remote_imei_services');
    }
};
