<?php

namespace Tests\Feature;

use App\Models\FinanceAccount;
use App\Models\ImeiOrder;
use App\Models\ImeiService;
use App\Models\ProductOrder;
use App\Support\CustomerOverview;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerOverviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('finance_accounts', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('locked_amount', 12, 2)->default(0); $table->decimal('total_receipts', 12, 2)->default(0);
            $table->decimal('paid_credits', 12, 2)->default(0); $table->decimal('overdraft_limit', 12, 2)->default(0); $table->timestamps();
        });
        foreach (['imei_orders', 'product_orders'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id(); $table->unsignedBigInteger('user_id'); $table->string('status');
                $table->decimal('price', 12, 4)->nullable(); $table->decimal('order_price', 12, 4)->nullable();
                $table->text('request')->nullable(); $table->boolean('processing')->default(false); $table->timestamps();
            });
        }
    }

    public function test_locked_amount_uses_active_customer_charges_without_double_counting_product_links(): void
    {
        ImeiOrder::forceCreate(['user_id'=>7,'status'=>'waiting','price'=>12,'order_price'=>5,'request'=>['charged_amount'=>12]]);
        ImeiOrder::forceCreate(['user_id'=>7,'status'=>'inprogress','price'=>30,'request'=>['charged_amount'=>0,'product_order_id'=>44]]);
        ImeiOrder::forceCreate(['user_id'=>7,'status'=>'success','price'=>99,'request'=>['charged_amount'=>99]]);
        ProductOrder::forceCreate(['user_id'=>7,'status'=>'inprogress','order_price'=>30,'request'=>['charged_amount'=>30]]);
        ProductOrder::forceCreate(['user_id'=>8,'status'=>'waiting','order_price'=>50,'request'=>['charged_amount'=>50]]);
        FinanceAccount::create(['user_id'=>7,'total_receipts'=>271.26,'locked_amount'=>0,'paid_credits'=>0,'overdraft_limit'=>0]);

        $overview = app(CustomerOverview::class);
        $this->assertSame(42.0, $overview->lockedAmount(7));
        $this->assertSame(271.26, $overview->totalReceipts(7));
    }

    public function test_translated_service_name_and_customer_price_are_presented_cleanly(): void
    {
        $order = new ImeiOrder(['price'=>9.29,'order_price'=>4.10]);
        $order->setRelation('service', new ImeiService(['name'=>'{"en":"Clean service","fallback":"Fallback"}']));
        $overview = app(CustomerOverview::class);

        $this->assertSame('Clean service', $overview->serviceName($order, 'imei'));
        $this->assertSame(9.29, $overview->orderAmount($order));
    }
}
