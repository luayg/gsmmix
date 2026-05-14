<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->foreignId('local_source_id')
                ->nullable()
                ->after('user_id')
                ->constrained('local_sources')
                ->nullOnDelete();

            $table->foreignId('local_reply_id')
                ->nullable()
                ->after('local_source_id')
                ->constrained('local_replies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('local_reply_id');
            $table->dropConstrainedForeignId('local_source_id');
        });
    }
};
