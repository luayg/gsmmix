<?php

namespace Tests\Feature;

use App\Models\ServerOrder;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServerOrderQuantityBillingTest extends TestCase
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
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('status')->default('active');
            $table->string('password')->default('test');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('server_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('device')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('remote_id')->nullable();
            $table->string('status')->default('waiting');
            $table->decimal('order_price', 12, 2)->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('profit', 12, 2)->default(0);
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
            'name' => 'Server billing user',
            'email' => 'server-billing@example.test',
            'username' => 'server-billing',
            'balance' => $balance,
            'status' => 'active',
            'password' => 'test-password',
        ]);
    }

    public function test_server_quantity_charges_each_unit_and_stores_total_refund_amount(): void
    {
        $user = $this->user('100.00');

        $order = DB::transaction(function () use ($user): ServerOrder {
            // Simulate BaseOrdersController's existing first-unit debit.
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $locked->balance = '90.00';
            $locked->save();

            return ServerOrder::create([
                'quantity' => 3,
                'price' => '10.00',
                'order_price' => '4.00',
                'profit' => '6.00',
                'user_id' => $user->id,
                'request' => [
                    'charged_amount' => 10,
                    'request_uid' => 'server-quantity-test',
                ],
            ]);
        });

        $this->assertSame('70.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame('30.00', number_format((float)$order->price, 2, '.', ''));
        $this->assertSame('12.00', number_format((float)$order->order_price, 2, '.', ''));
        $this->assertSame('18.00', number_format((float)$order->profit, 2, '.', ''));
        $this->assertSame(30.0, (float)data_get($order->request, 'charged_amount'));
        $this->assertSame(3, (int)data_get($order->request, 'billing_quantity'));
        $this->assertSame('server_per_unit', data_get($order->request, 'billing_mode'));
    }

    public function test_insufficient_balance_rolls_back_the_first_unit_debit_and_order(): void
    {
        $user = $this->user('25.00');
        $caught = null;

        try {
            DB::transaction(function () use ($user): void {
                $locked = User::query()->lockForUpdate()->findOrFail($user->id);
                $locked->balance = '15.00';
                $locked->save();

                ServerOrder::create([
                    'quantity' => 3,
                    'price' => '10.00',
                    'order_price' => '4.00',
                    'profit' => '6.00',
                    'user_id' => $user->id,
                    'request' => [
                        'charged_amount' => 10,
                        'request_uid' => 'server-insufficient-test',
                    ],
                ]);
            });
        } catch (\RuntimeException $exception) {
            $caught = $exception;
        }

        $this->assertNotNull($caught);
        $this->assertSame('INSUFFICIENT_BALANCE', $caught->getMessage());
        $this->assertSame('25.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame(0, ServerOrder::count());
    }

    public function test_legacy_direct_provider_order_without_request_uid_is_not_rebilled(): void
    {
        $user = $this->user('100.00');

        $order = ServerOrder::create([
            'quantity' => 3,
            'price' => '10.00',
            'order_price' => '4.00',
            'profit' => '6.00',
            'user_id' => $user->id,
            'request' => ['ID' => 123, 'QUANTITY' => 3],
        ]);

        $this->assertSame('100.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$order->price, 2, '.', ''));
        $this->assertNull(data_get($order->request, 'server_quantity_billing_applied'));
    }
}
