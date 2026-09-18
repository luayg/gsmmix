<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pages', fn(Blueprint $table) => $table->json('style')->nullable()->after('og_image'));
        Schema::create('page_themes', function(Blueprint $table): void {
            $table->id(); $table->string('name'); $table->json('settings');
            $table->boolean('active')->default(false)->index(); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('page_themes');
        Schema::table('pages', fn(Blueprint $table) => $table->dropColumn('style'));
    }
};
