<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->decimal('tax_percent', 8, 4)->default(0)->after('percent_fee');
        });

        Schema::create('mail_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 150);
            $table->string('subject', 255);
            $table->longText('body');
            $table->enum('audience', ['user', 'admin', 'both'])->default('user');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
        Schema::table('payment_gateways', fn (Blueprint $table) => $table->dropColumn('tax_percent'));
    }
};
