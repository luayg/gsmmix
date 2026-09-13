<?php

namespace Tests\Feature;

use App\Models\ImeiOrder;
use App\Services\Orders\OrderDispatchClaimService;
use App\Services\Orders\OrderDispatcher;
use App\Services\Orders\OrderFinanceService;
use App\Services\Orders\OrderSender;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class OrderDispatchClaimTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('imei_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('remote_id')->nullable();
            $table->string('status')->default('waiting');
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->boolean('api_order')->default(false);
            $table->boolean('processing')->default(false);
            $table->timestamps();
        });
    }

    public function test_only_one_worker_can_claim_the_same_waiting_order(): void
    {
        $order = ImeiOrder::create([
            'status' => 'waiting',
            'api_order' => true,
            'processing' => false,
            'request' => [],
        ]);

        $claims = app(OrderDispatchClaimService::class);
        $first = $claims->claim(ImeiOrder::class, $order->id);
        $second = $claims->claim(ImeiOrder::class, $order->id);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertTrue((bool)$order->fresh()->processing);
        $this->assertSame('inprogress', $order->fresh()->status);
        $this->assertSame(1, (int)data_get($order->fresh()->request, 'dispatch_attempt'));
    }

    public function test_unexpected_local_failure_releases_an_unresolved_claim_for_retry(): void
    {
        $order = ImeiOrder::create([
            'status' => 'waiting',
            'api_order' => true,
            'processing' => false,
            'request' => [],
        ]);

        $claims = app(OrderDispatchClaimService::class);
        $this->assertNotNull($claims->claim(ImeiOrder::class, $order->id));
        $claims->releaseUnexpectedFailure(ImeiOrder::class, $order->id);

        $fresh = $order->fresh();
        $this->assertFalse((bool)$fresh->processing);
        $this->assertSame('waiting', $fresh->status);
        $this->assertNotEmpty(data_get($fresh->request, 'dispatch_failed_at'));
        $this->assertNotNull($claims->claim(ImeiOrder::class, $order->id));
    }

    public function test_sent_or_terminal_orders_cannot_be_claimed(): void
    {
        $claims = app(OrderDispatchClaimService::class);

        $sent = ImeiOrder::create([
            'remote_id' => 'REMOTE-1', 'status' => 'waiting', 'api_order' => true, 'processing' => false,
        ]);
        $done = ImeiOrder::create([
            'status' => 'success', 'api_order' => true, 'processing' => false,
        ]);

        $this->assertNull($claims->claim(ImeiOrder::class, $sent->id));
        $this->assertNull($claims->claim(ImeiOrder::class, $done->id));
    }

    public function test_dispatcher_refuses_unclaimed_waiting_order_before_provider_call(): void
    {
        $order = ImeiOrder::create([
            'status' => 'waiting',
            'api_order' => true,
            'processing' => false,
            'request' => [],
        ]);

        $sender = Mockery::mock(OrderSender::class);
        $sender->shouldNotReceive('sendImei');
        $finance = Mockery::mock(OrderFinanceService::class);

        $dispatcher = new OrderDispatcher($sender, $finance);
        $dispatcher->send('imei', $order->id);

        $fresh = $order->fresh();
        $this->assertSame('waiting', $fresh->status);
        $this->assertFalse((bool)$fresh->processing);
    }
}
