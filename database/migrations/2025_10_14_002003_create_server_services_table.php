<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_services', function (Blueprint $table) {
            $table->increments('id');

            $table->string('icon')->nullable();
            $table->string('alias')->index();
            $table->text('name')->nullable();
            $table->text('time')->nullable();
            $table->longText('info')->nullable();

            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('profit', 12, 2)->default(0);
            $table->integer('profit_type')->default(1);

            $table->text('main_field')->nullable();
            $table->text('params')->nullable();

            $table->boolean('active')->default(1);
            $table->boolean('allow_bulk')->default(1);
            $table->boolean('allow_duplicates')->default(0);
            $table->boolean('reply_with_latest')->default(0);
            $table->boolean('allow_report')->default(1);
            $table->integer('allow_report_time')->default(0);   // ← واحدة فقط
            $table->boolean('allow_cancel')->default(0);
            $table->integer('allow_cancel_time')->default(0);

            $table->boolean('use_remote_cost')->default(0);
            $table->boolean('use_remote_price')->default(0);
            $table->boolean('stop_on_api_change')->default(0);
            $table->boolean('needs_approval')->default(0);

            $table->integer('reply_expiration')->default(0);
            $table->longText('expiration_text')->nullable();

            $table->string('type')->default('server'); // كما في المثال
            $table->unsignedInteger('group_id')->nullable();
            $table->integer('source')->nullable();
            $table->unsignedInteger('remote_id')->nullable();
            $table->unsignedInteger('supplier_id')->nullable();
            $table->unsignedInteger('local_source_id')->nullable();

            $table->boolean('device_based')->default(0);
            $table->boolean('reject_on_missing_reply')->default(0);

            $table->integer('ordering')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_services');
    }
};
