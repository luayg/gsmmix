<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table): void {
            $table->decimal('balance_before', 14, 4)->nullable()->after('note');
            $table->char('currency_code', 3)->nullable()->after('amount');
            $table->decimal('original_amount', 14, 4)->nullable()->after('currency_code');
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('original_amount');
            $table->nullableMorphs('source');
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->json('customer_snapshot');
            $table->json('company_snapshot')->nullable();
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('discount_total', 14, 4)->default(0);
            $table->decimal('tax_total', 14, 4)->default(0);
            $table->decimal('fee_total', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->decimal('paid_total', 14, 4)->default(0);
            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->nullableMorphs('source');
            $table->timestamps();
            $table->index(['user_id', 'issued_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('discount', 14, 4)->default(0);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('line_total', 14, 4);
            $table->unsignedInteger('ordering')->default(0);
            $table->timestamps();
        });

        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->foreignId('finance_transaction_id')->nullable()->constrained('finance_transactions')->nullOnDelete();
            $table->decimal('amount', 14, 4);
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->string('reference')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('status', 16)->default('draft')->index();
            $table->string('placement', 16)->default('standalone');
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('featured_image')->nullable();
            $table->string('og_image')->nullable();
            $table->boolean('authenticated_only')->default(false);
            $table->boolean('open_new_window')->default(false);
            $table->boolean('system')->default(false);
            $table->unsignedInteger('ordering')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('page_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
            $table->unique(['page_id', 'language_id']);
        });

        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('location')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('url')->nullable();
            $table->boolean('open_new_window')->default(false);
            $table->unsignedInteger('ordering')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::table('finance_transactions', function (Blueprint $table): void {
            $table->dropMorphs('source');
            $table->dropColumn(['balance_before', 'currency_code', 'original_amount', 'exchange_rate']);
        });
    }
};
