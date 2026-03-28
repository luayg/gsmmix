<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_smm_services', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('api_provider_id');
            $table->string('group_name')->nullable();
            $table->string('remote_id');
            $table->string('name');

            $table->string('type')->nullable();
            $table->string('category')->nullable();

            $table->decimal('price', 12, 4)->default(0);
            $table->unsignedInteger('min')->nullable();
            $table->unsignedInteger('max')->nullable();

            $table->boolean('refill')->default(false);
            $table->boolean('cancel')->default(false);

            $table->string('time')->nullable();

            $table->json('additional_fields')->nullable();
            $table->json('additional_data')->nullable();
            $table->json('params')->nullable();

            $table->timestamps();

            $table->index(['api_provider_id']);
            $table->index(['remote_id']);
            $table->index(['type']);
            $table->index(['category']);
            $table->unique(['api_provider_id', 'remote_id'], 'remote_smm_provider_remote_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_smm_services');
    }
};