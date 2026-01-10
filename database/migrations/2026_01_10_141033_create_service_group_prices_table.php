<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('service_group_prices', function (Blueprint $table) {
      $table->id();

      // service
      $table->unsignedBigInteger('service_id');
      $table->string('service_kind', 20)->default('imei'); // imei/server/file

      // group
      $table->unsignedBigInteger('group_id');

      // values
      $table->decimal('price', 12, 2)->default(0);
      $table->decimal('discount', 12, 2)->default(0);

      $table->timestamps();

      $table->unique(['service_id','service_kind','group_id'], 'sgp_unique');
      $table->index(['service_kind']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('service_group_prices');
  }
};
