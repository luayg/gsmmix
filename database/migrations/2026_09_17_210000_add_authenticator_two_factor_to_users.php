<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('two_factor_method', 20)->default('email')->after('two_factor_enabled');
            $table->text('two_factor_secret')->nullable()->after('two_factor_method');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });
    }
    public function down(): void
    {
        Schema::table('users', fn(Blueprint $table)=>$table->dropColumn(['two_factor_method','two_factor_secret','two_factor_confirmed_at']));
    }
};
