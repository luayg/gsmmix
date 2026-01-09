<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('file_services', function (Blueprint $t) {
      $t->increments('id');
      $t->string('icon',255)->nullable();
      $t->string('alias',255);
      $t->text('name')->nullable();
      $t->text('time')->nullable();
      $t->longText('info')->nullable();

      $t->decimal('cost',12,2)->default(0.00);
      $t->decimal('profit',12,2)->default(0.00);
      $t->integer('profit_type')->default(1);

      $t->text('main_field')->nullable();
      $t->text('params')->nullable();

      $t->boolean('active')->default(1);
      $t->boolean('allow_bulk')->default(1);
      $t->boolean('allow_duplicates')->default(0);
      $t->boolean('reply_with_latest')->default(0);

      $t->boolean('allow_report')->default(1);
      $t->integer('allow_report_time')->default(0);

      $t->boolean('allow_cancel')->default(0);
      $t->integer('allow_cancel_time')->default(0);

      $t->boolean('use_remote_cost')->default(0);
      $t->boolean('use_remote_price')->default(0);
      $t->boolean('stop_on_api_change')->default(0);

      $t->boolean('needs_approval')->default(0);
      $t->integer('reply_expiration')->default(0);
      $t->longText('expiration_text')->nullable();

      $t->string('type',255)->default('server');

      $t->unsignedInteger('group_id')->nullable();
      $t->integer('source')->nullable();
      $t->unsignedInteger('remote_id')->nullable();
      $t->unsignedInteger('supplier_id')->nullable();
      $t->unsignedInteger('local_source_id')->nullable();

      $t->boolean('device_based')->default(0);
      $t->boolean('reject_on_missing_reply')->default(0);

      $t->integer('ordering')->default(1);

      $t->timestamp('created_at')->nullable();
      $t->timestamp('updated_at')->nullable();

      $t->index('alias','file_services_alias_index');
      $t->index('group_id','file_services_group_id_foreign');
      $t->index('remote_id','file_services_remote_id_foreign');
      $t->index('supplier_id','file_services_supplier_id_foreign');
      $t->index('local_source_id','file_services_local_source_id_foreign');

      $t->foreign('group_id')->references('id')->on('service_groups')->nullOnDelete();
      // $t->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
      // $t->foreign('local_source_id')->references('id')->on('local_sources')->nullOnDelete();
    });
  }
  public function down(): void { Schema::dropIfExists('file_services'); }
};
