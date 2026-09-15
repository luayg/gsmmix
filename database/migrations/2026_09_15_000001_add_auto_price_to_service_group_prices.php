<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_group_prices', function (Blueprint $table): void {
            // Existing prices may be intentional, even when equal to the service price.
            $table->boolean('auto_price')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('service_group_prices', function (Blueprint $table): void {
            $table->dropColumn('auto_price');
        });
    }
};
