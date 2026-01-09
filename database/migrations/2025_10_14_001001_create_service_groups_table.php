<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('service_groups', function (Blueprint $t) {
            $t->increments('id');
            $t->text('name');                                  // NOT NULL
            $t->string('type', 255)->default('imei_service');  // NOT NULL DEFAULT 'imei_service'
            $t->integer('ordering')->default(1);               // NOT NULL DEFAULT 1
            $t->timestamp('created_at')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('service_groups'); }
};
