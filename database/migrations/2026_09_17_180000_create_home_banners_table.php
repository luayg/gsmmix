<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('home_banners', function (Blueprint $table): void {
            $table->id();
            $table->string('image_path');
            $table->string('title', 120)->nullable();
            $table->string('text', 500)->nullable();
            $table->string('button_label', 50)->nullable();
            $table->string('button_url', 1000)->nullable();
            $table->unsignedInteger('ordering')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'ordering']);
        });
    }

    public function down(): void { Schema::dropIfExists('home_banners'); }
};
