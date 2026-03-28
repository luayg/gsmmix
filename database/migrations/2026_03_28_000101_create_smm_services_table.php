<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smm_services', function (Blueprint $table) {
            $table->id();

            $table->string('icon')->nullable();
            $table->string('alias')->unique();
            $table->json('name')->nullable();
            $table->json('time')->nullable();
            $table->json('info')->nullable();

            $table->decimal('cost', 12, 4)->default(0);
            $table->decimal('profit', 12, 4)->default(0);
            $table->unsignedTinyInteger('profit_type')->default(1);

            $table->json('main_field')->nullable();
            $table->json('params')->nullable();

            $table->boolean('active')->default(true);
            $table->boolean('allow_bulk')->default(false);
            $table->boolean('allow_duplicates')->default(false);
            $table->boolean('reply_with_latest')->default(false);

            $table->boolean('allow_report')->default(false);
            $table->unsignedInteger('allow_report_time')->default(0);

            $table->boolean('allow_cancel')->default(false);
            $table->unsignedInteger('allow_cancel_time')->default(0);

            $table->boolean('use_remote_cost')->default(false);
            $table->boolean('use_remote_price')->default(false);
            $table->boolean('stop_on_api_change')->default(false);
            $table->boolean('needs_approval')->default(false);

            $table->unsignedInteger('reply_expiration')->default(0);
            $table->json('expiration_text')->nullable();

            $table->string('type')->default('smm');
            $table->unsignedBigInteger('group_id')->nullable();

            $table->unsignedTinyInteger('source')->default(1);
            $table->string('remote_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('local_source_id')->nullable();

            $table->boolean('device_based')->default(false);
            $table->boolean('reject_on_missing_reply')->default(false);
            $table->integer('ordering')->default(0);

            $table->timestamps();

            $table->index(['group_id']);
            $table->index(['supplier_id']);
            $table->index(['source']);
            $table->index(['remote_id']);
            $table->index(['type']);
            $table->index(['active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smm_services');
    }
};