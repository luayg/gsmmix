<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->string('driver', 40)->change();
            $table->boolean('is_system')->default(false)->after('driver')->index();
        });

        foreach ([
            ['name' => 'PayPal Express Checkout', 'slug' => 'paypal', 'driver' => 'paypal', 'ordering' => 10],
            ['name' => 'Binance Pay Merchant', 'slug' => 'binance-pay', 'driver' => 'binance_pay', 'ordering' => 20],
            ['name' => 'USDT Auto Pay', 'slug' => 'usdt', 'driver' => 'usdt', 'ordering' => 30],
        ] as $gateway) {
            DB::table('payment_gateways')->updateOrInsert(
                ['slug' => $gateway['slug']],
                $gateway + [
                    'is_system' => true, 'active' => false, 'sandbox' => true,
                    'fixed_fee' => 0, 'percent_fee' => 0, 'tax_percent' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('payment_gateways')->whereIn('slug', ['paypal', 'binance-pay', 'usdt'])->where('is_system', true)->delete();
        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->dropColumn('is_system');
            $table->enum('driver', ['manual', 'stripe', 'paypal', 'custom'])->change();
        });
    }
};
