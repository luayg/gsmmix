<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->enum('driver', ['manual', 'stripe', 'paypal', 'custom']);
            $table->text('description')->nullable();
            $table->longText('instructions')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('config')->nullable();
            $table->longText('credentials')->nullable();
            $table->decimal('fixed_fee', 20, 8)->default(0);
            $table->decimal('percent_fee', 8, 4)->default(0);
            $table->decimal('minimum_amount', 20, 8)->nullable();
            $table->decimal('maximum_amount', 20, 8)->nullable();
            $table->boolean('sandbox')->default(true);
            $table->boolean('active')->default(false)->index();
            $table->unsignedInteger('ordering')->default(0);
            $table->timestamps();
        });
        Schema::create('currency_payment_gateway', function (Blueprint $table): void {
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_gateway_id')->constrained()->cascadeOnDelete();
            $table->primary(['currency_id', 'payment_gateway_id']);
        });
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_gateway_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 20, 8);
            $table->decimal('amount_base', 20, 8);
            $table->decimal('fee_base', 20, 8)->default(0);
            $table->decimal('payable_base', 20, 8);
            $table->decimal('payable_currency', 20, 8);
            $table->enum('status', ['pending', 'review', 'paid', 'rejected', 'cancelled', 'refunded'])->default('pending')->index();
            $table->string('external_id')->nullable()->index();
            $table->string('proof_path')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('approved_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_gateway_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_id')->nullable();
            $table->char('payload_hash', 64);
            $table->enum('status', ['received', 'processed', 'ignored', 'failed'])->default('received');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['payment_gateway_id', 'event_id']);
            $table->index(['payload_hash', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('currency_payment_gateway');
        Schema::dropIfExists('payment_gateways');
    }
};
