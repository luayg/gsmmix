<?php

namespace Tests\Feature;

use App\Models\ImeiOrder;
use App\Services\Orders\DispatchHoldRecoveryService;
use App\Services\Orders\OrderDispatchClaimService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DispatchHoldRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('imei_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('device')->nullable();
            $table->string('remote_id')->nullable();
            $table->string('status')->default('waiting');
            $table->decimal('order_price', 12, 4)->default(0);
            $table->decimal('price', 12, 4)->default(0);
            $table->decimal('profit', 12, 4)->default(0);
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->text('comments')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->boolean('needs_verify')->default(false);
            $table->boolean('expired')->default(false);
            $table->boolean('approved')->default(false);
            $table->string('ip')->nullable();
            $table->boolean('api_order')->default(true);
            $table->text('params')->nullable();
            $table->boolean('processing')->default(false);
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });
    }

    private function heldOrder(array $extraRequest = []): ImeiOrder
    {
        return ImeiOrder::create([
            'device' => '356789012345678',
            'api_order' => true,
            'status' => 'waiting',
            'processing' => false,
            'price' => '20.0000',
            'request' => array_merge([
                'charged_amount' => 20,
                'financial_state' => 'charged',
                'request' => [
                    'dispatch_hold' => true,
                    'contract_error' => 'provider_ack_without_remote_id',
                ],
            ], $extraRequest),
            'response' => [
                'type' => 'queued',
                'message' => 'Provider acknowledged without an order ID',
            ],
        ]);
    }

    public function test_held_order_cannot_be_claimed_for_automatic_resubmission(): void
    {
        $order = $this->heldOrder();

        $claimed = app(OrderDispatchClaimService::class)->claim(ImeiOrder::class, $order->id);

        $this->assertNull($claimed);
        $fresh = $order->fresh();
        $this->assertSame('waiting', $fresh->status);
        $this->assertFalse((bool)$fresh->processing);
        $this->assertNull($fresh->remote_id);
    }

    public function test_operator_can_attach_recovered_remote_id_without_changing_financial_state(): void
    {
        $order = $this->heldOrder();

        $resolved = app(DispatchHoldRecoveryService::class)->resolve(
            'imei',
            $order->id,
            'REMOTE-9001',
            'Found in provider dashboard'
        );

        $this->assertSame('REMOTE-9001', $resolved->remote_id);
        $this->assertSame('inprogress', $resolved->status);
        $this->assertTrue((bool)$resolved->processing);
        $this->assertSame('charged', data_get($resolved->request, 'financial_state'));
        $this->assertFalse((bool)data_get($resolved->request, 'request.dispatch_hold'));
        $this->assertSame('REMOTE-9001', data_get($resolved->request, 'dispatch_hold_resolved_remote_id'));
        $this->assertSame('Found in provider dashboard', data_get($resolved->request, 'dispatch_hold_resolution_note'));
        $this->assertSame('REMOTE-9001', data_get($resolved->response, 'reference_id'));

        // Once remote_id exists, it is impossible to claim this order as a new submission.
        $claimed = app(OrderDispatchClaimService::class)->claim(ImeiOrder::class, $resolved->id);
        $this->assertNull($claimed);
    }

    public function test_refunded_hold_requires_manual_financial_review_before_remote_id_is_attached(): void
    {
        $order = $this->heldOrder(['financial_state' => 'refunded', 'refunded_at' => now()->toDateTimeString()]);

        try {
            app(DispatchHoldRecoveryService::class)->resolve('imei', $order->id, 'REMOTE-LATE');
            $this->fail('Expected refunded hold recovery to be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertSame('DISPATCH_HOLD_ORDER_REFUNDED', $e->getMessage());
        }

        $fresh = $order->fresh();
        $this->assertNull($fresh->remote_id);
        $this->assertSame('waiting', $fresh->status);
        $this->assertSame('refunded', data_get($fresh->request, 'financial_state'));
        $this->assertTrue((bool)data_get($fresh->request, 'request.dispatch_hold'));
    }

    public function test_non_held_order_cannot_be_resolved_through_recovery_command(): void
    {
        $order = ImeiOrder::create([
            'api_order' => true,
            'status' => 'waiting',
            'processing' => false,
            'request' => ['financial_state' => 'charged'],
        ]);

        $this->artisan('orders:resolve-dispatch-hold', [
            'kind' => 'imei',
            'id' => $order->id,
            'remote_id' => 'REMOTE-INVALID',
        ])->expectsOutputToContain('DISPATCH_HOLD_NOT_FOUND')->assertFailed();

        $this->assertNull($order->fresh()->remote_id);
    }
}
