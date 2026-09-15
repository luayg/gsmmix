<?php

namespace Tests\Unit;

use App\Models\ServiceGroupPrice;
use App\Models\SmmService;
use App\Models\User;
use App\Services\Orders\SmmPricingException;
use App\Services\Orders\SmmPricingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SmmPricingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('service_group_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->string('service_type');
            $table->unsignedBigInteger('group_id');
            $table->decimal('price', 12, 4)->default(0);
            $table->boolean('auto_price')->default(false);
            $table->decimal('discount', 12, 4)->default(0);
            $table->tinyInteger('discount_type')->default(1);
            $table->timestamps();
        });
    }

    private function service(string $type, float $cost = 2.0, float $profit = 0.5, int $profitType = 1): SmmService
    {
        $service = new SmmService();
        $service->id = 10;
        $service->cost = $cost;
        $service->profit = $profit;
        $service->profit_type = $profitType;
        $service->params = [
            'smm_type' => $type,
            'smm_limits' => ['min' => 1, 'max' => 1000000],
        ];
        return $service;
    }

    private function user(int $groupId = 0): User
    {
        $user = new User();
        $user->id = 20;
        $user->group_id = $groupId ?: null;
        return $user;
    }

    public function test_default_rate_is_priced_per_thousand_units(): void
    {
        $quote = app(SmmPricingService::class)->quote($this->service('Default'), $this->user(), ['quantity' => 500], 1);

        $this->assertSame('per_1000_quantity', $quote['billing_mode']);
        $this->assertSame(500, $quote['billable_units']);
        $this->assertSame('1.2500', $quote['sell_total']);
        $this->assertSame('1.0000', $quote['provider_total']);
        $this->assertSame('0.2500', $quote['profit_total']);
    }

    public function test_package_rate_is_the_full_order_price(): void
    {
        $quote = app(SmmPricingService::class)->quote($this->service('Package'), $this->user(), [], 999);

        $this->assertSame('per_order', $quote['billing_mode']);
        $this->assertSame(1, $quote['effective_quantity']);
        $this->assertSame('2.5000', $quote['sell_total']);
        $this->assertSame('2.0000', $quote['provider_total']);
    }

    public function test_drip_feed_bills_quantity_per_run_times_runs(): void
    {
        $quote = app(SmmPricingService::class)->quote(
            $this->service('Drip-feed'),
            $this->user(),
            ['quantity' => 100, 'runs' => 10],
            1
        );

        $this->assertSame('per_1000_runs', $quote['billing_mode']);
        $this->assertSame(1000, $quote['billable_units']);
        $this->assertSame('2.5000', $quote['sell_total']);
    }

    public function test_custom_comments_bill_nonempty_lines(): void
    {
        $quote = app(SmmPricingService::class)->quote(
            $this->service('Custom Comments', 8.0, 2.0),
            $this->user(),
            ['comments' => "one\n\ntwo\nthree"],
            1
        );

        $this->assertSame('per_1000_lines_comments', $quote['billing_mode']);
        $this->assertSame(3, $quote['billable_units']);
        $this->assertSame('0.0300', $quote['sell_total']);
        $this->assertSame('0.0240', $quote['provider_total']);
    }

    public function test_fixed_subscription_can_be_precharged_but_variable_subscription_is_rejected(): void
    {
        $service = $this->service('Subscriptions', 5.0, 1.0);
        $quote = app(SmmPricingService::class)->quote(
            $service,
            $this->user(),
            ['min' => 100, 'max' => 100, 'posts' => 5, 'old_posts' => 2],
            1
        );

        $this->assertSame('subscription_fixed', $quote['billing_mode']);
        $this->assertSame(700, $quote['billable_units']);
        $this->assertSame('4.2000', $quote['sell_total']);

        $this->expectException(SmmPricingException::class);
        app(SmmPricingService::class)->quote(
            $service,
            $this->user(),
            ['min' => 100, 'max' => 150, 'posts' => 5],
            1
        );
    }

    public function test_zero_group_rate_and_automatic_discounts_are_used_by_the_quote(): void
    {
        $row = ServiceGroupPrice::create(['service_id' => 10, 'service_type' => 'smm',
            'group_id' => 7, 'price' => 0, 'auto_price' => false, 'discount' => 0, 'discount_type' => 1]);
        $pricing = app(SmmPricingService::class);
        $quote = $pricing->quote($this->service('Default'), $this->user(7), ['quantity' => 1000], 1);
        $this->assertSame('0.0000', $quote['sell_rate']);
        $this->assertSame('0.0000', $quote['sell_total']);
        $this->assertSame('2.0000', $quote['provider_total']);
        $this->assertSame('-2.0000', $quote['profit_total']);

        $row->update(['price' => 999, 'auto_price' => true, 'discount' => 10, 'discount_type' => 2]);
        $quote = $pricing->quote($this->service('Default', 20, 5), $this->user(7), ['quantity' => 500], 1);
        $this->assertSame('22.5000', $quote['sell_rate']);
        $this->assertSame('11.2500', $quote['sell_total']);
        $quote = $pricing->quote($this->service('Default', 100, 10, 2), $this->user(7), ['quantity' => 1000], 1);
        $this->assertSame('99.0000', $quote['sell_total']);
        $row->update(['discount' => 100]);
        $quote = $pricing->quote($this->service('Package'), $this->user(7), [], 1);
        $this->assertSame('0.0000', $quote['sell_total']);
    }

    public function test_smm_group_price_is_used_by_backend_quote(): void
    {
        ServiceGroupPrice::create([
            'service_id' => 10,
            'service_type' => 'smm',
            'group_id' => 7,
            'price' => 3.0000,
            'discount' => 0.5000,
            'discount_type' => 1,
        ]);

        $quote = app(SmmPricingService::class)->quote(
            $this->service('Default'),
            $this->user(7),
            ['quantity' => 1000],
            1
        );

        $this->assertSame('2.5000', $quote['sell_rate']);
        $this->assertSame('2.5000', $quote['sell_total']);
    }
}
