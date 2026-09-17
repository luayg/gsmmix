<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('downloads', function (Blueprint $table) {
            $table->string('visibility', 20)->default('public')->index()->after('active');
            $table->decimal('price', 12, 2)->default(0)->after('visibility');
        });
        DB::table('downloads')->where('is_free', false)->update(['visibility' => 'private']);

        Schema::create('download_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('download_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->decimal('balance_before', 14, 4);
            $table->decimal('balance_after', 14, 4);
            $table->timestamps();
            $table->unique(['download_id', 'user_id']);
        });

        Schema::create('resellers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 50);
            $table->string('whatsapp', 50)->nullable();
            $table->string('country', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('ordering')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resellers');
        Schema::dropIfExists('download_purchases');
        Schema::table('downloads', fn (Blueprint $table) => $table->dropColumn(['visibility', 'price']));
    }
};
