<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('native_name', 100);
            $table->string('code', 10)->unique();
            $table->string('locale', 20)->unique();
            $table->enum('direction', ['ltr', 'rtl'])->default('ltr');
            $table->string('flag', 10)->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('ordering')->default(0);
            $table->timestamps();
        });
        Schema::create('language_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('translation_key', 191);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['language_id', 'translation_key']);
            $table->index('translation_key');
        });
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name', 100);
            $table->string('symbol', 10);
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->enum('symbol_position', ['before', 'after'])->default('before');
            $table->char('decimal_separator', 1)->default('.');
            $table->char('thousands_separator', 1)->default(',');
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('ordering')->default(0);
            $table->timestamp('rate_updated_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('languages')->insert([
            'name' => 'English', 'native_name' => 'English', 'code' => 'en', 'locale' => 'en',
            'direction' => 'ltr', 'is_default' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('currencies')->insert([
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 1,
            'is_default' => true, 'active' => true, 'rate_updated_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('language_translations');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('currencies');
    }
};
