<?php

namespace Tests\Feature;

use App\Models\ImeiOrder;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderStatusFinanceObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->decimal('balance', 12, 4)->default(0);
            $table->string('status')->default('active');
            $table->string('password')->default('test');
            $table->rememberToken();
            $table->timestamps();
        });

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
            $table->boolean('api_order')->default(false);
            $table->text('params')->nullable();
            $table->boolean('processing')->default(false);
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });
    }

    private function user(string $balance): User
    {
        return User::create([
            'name' => 'Status finance user',
            'email' => 'status-finance@example.test',
            'username' => 'status-finance',
            'balance' => $balance,
            'status' => 'active',
            'password' => 'test-password',
        ]);
    }

    private function order(User $user, string $status = 'inprogress'): ImeiOrder
    {
        return ImeiOrder::create([
            'status' => $status,
            'price' => '20.0000',
            'user_id' => $user->id,
            'request' => [
                'charged_amount' => 20,
                'financial_state' => 'charged',
            ],
            'response' => [],
            'processing' => $status === 'inprogress',
        ]);
    }

    public function test_rejected_status_change_refunds_even_without_an_explicit_controller_refund_call(): void
    {
        $user = $this->user('80.0000');
        $order = $this->order($user);

        $order->status = 'rejected';
        $order->processing = false;
        $order->save();

        $this->assertSame('100.0000', number_format((float)$user->fresh()->balance, 4, '.', ''));
        $this->assertSame('refunded', data_get($order->fresh()->request, 'financial_state'));
    }

    public function test_provider_or_background_success_recharges_a_refunded_order_even_if_refund_was_spent(): void
    {
        $user = $this->user('80.0000');
        $order = $this->order($user);
        $order->status = 'rejected';
        $order->processing = false;
        $order->save();

        $user->fresh()->forceFill(['balance' => '5.0000'])->save();

        $order = $order->fresh();
        $order->status = 'success';
        $order->processing = false;
        $order->save();

        $fresh = $order->fresh();
        $this->assertSame('-15.0000', number_format((float)$user->fresh()->balance, 4, '.', ''));
        $this->assertSame('charged', data_get($fresh->request, 'financial_state'));
        $this->assertTrue((bool)data_get($fresh->request, 'recharged_with_negative_balance'));
    }

    public function test_non_status_updates_do_not_repeat_financial_actions(): void
    {
        $user = $this->user('80.0000');
        $order = $this->order($user);
        $order->status = 'rejected';
        $order->processing = false;
        $order->save();

        $order = $order->fresh();
        $order->comments = 'Reviewed';
        $order->save();

        $this->assertSame('100.0000', number_format((float)$user->fresh()->balance, 4, '.', ''));
    }

    public function test_status_audit_detects_and_then_clears_financial_inconsistency(): void
    {
        $user = $this->user('100.0000');
        $order = ImeiOrder::create([
            'status' => 'success',
            'remote_id' => 'REMOTE-1',
            'price' => '20.0000',
            'user_id' => $user->id,
            'request' => [
                'charged_amount' => 20,
                'financial_state' => 'refunded',
                'refunded_at' => now()->toDateTimeString(),
            ],
            'processing' => false,
        ]);

        $this->artisan('orders:status-audit', ['--json' => true])
            ->expectsOutputToContain('"success_refunded":1')
            ->assertFailed();

        $order->status = 'inprogress';
        $order->save();
        $order->status = 'success';
        $order->processing = false;
        $order->save();

        $this->artisan('orders:status-audit', ['--json' => true])
            ->expectsOutputToContain('"success_refunded":0')
            ->assertSuccessful();
    }
}
