<?php

namespace Tests\Feature;

use App\Models\FileOrder;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FileOrderGroupPricingTest extends TestCase
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

        Schema::create('file_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('device')->nullable();
            $table->string('remote_id')->nullable();
            $table->string('status')->default('waiting');
            $table->decimal('order_price', 12, 2)->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('profit', 12, 2)->default(0);
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->text('comments')->nullable();
            $table->string('storage_path')->nullable();
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

        Schema::create('service_group_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->string('service_type');
            $table->unsignedBigInteger('group_id');
            $table->decimal('price', 12, 4)->default(0);
            $table->decimal('discount', 12, 4)->default(0);
            $table->unsignedTinyInteger('discount_type')->default(1);
            $table->timestamps();
        });
    }

    private function user(string $balance, int $groupId = 7): User
    {
        return User::create([
            'name' => 'File pricing user',
            'email' => 'file-pricing@example.test',
            'username' => 'file-pricing',
            'group_id' => $groupId,
            'balance' => $balance,
            'status' => 'active',
            'password' => 'test-password',
        ]);
    }

    private function groupPrice(int $serviceId, int $groupId, string $price, string $discount = '0.00', int $discountType = 1): void
    {
        DB::table('service_group_prices')->insert([
            'service_id' => $serviceId,
            'service_type' => 'file',
            'group_id' => $groupId,
            'price' => $price,
            'discount' => $discount,
            'discount_type' => $discountType,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_file_group_price_replaces_fallback_sell_price_and_adjusts_existing_debit(): void
    {
        $user = $this->user('100.00');
        $this->groupPrice(55, 7, '8.00');

        $order = DB::transaction(function () use ($user): FileOrder {
            // BaseOrdersController charged its fallback price before model creation.
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $locked->balance = '90.00';
            $locked->save();

            return FileOrder::create([
                'price' => '10.00',
                'order_price' => '5.00',
                'profit' => '5.00',
                'user_id' => $user->id,
                'service_id' => 55,
                'request' => [
                    'charged_amount' => 10,
                    'request_uid' => 'file-group-lower-test',
                ],
            ]);
        });

        $this->assertSame('92.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame('8.00', number_format((float)$order->price, 2, '.', ''));
        $this->assertSame('3.00', number_format((float)$order->profit, 2, '.', ''));
        $this->assertSame(8.0, (float)data_get($order->request, 'charged_amount'));
        $this->assertSame('file_group_price', data_get($order->request, 'billing_mode'));
        $this->assertTrue((bool)data_get($order->request, 'file_group_pricing_applied'));
    }

    public function test_file_group_discount_is_applied_and_extra_debit_is_charged(): void
    {
        $user = $this->user('100.00');
        $this->groupPrice(77, 7, '15.00', '20.00', 2); // effective 12.00

        $order = DB::transaction(function () use ($user): FileOrder {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $locked->balance = '90.00';
            $locked->save();

            return FileOrder::create([
                'price' => '10.00',
                'order_price' => '5.00',
                'profit' => '5.00',
                'user_id' => $user->id,
                'service_id' => 77,
                'request' => [
                    'charged_amount' => 10,
                    'request_uid' => 'file-group-higher-test',
                ],
            ]);
        });

        $this->assertSame('88.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame('12.00', number_format((float)$order->price, 2, '.', ''));
        $this->assertSame('7.00', number_format((float)$order->profit, 2, '.', ''));
    }

    public function test_legacy_file_order_without_request_uid_is_not_rebilled(): void
    {
        $user = $this->user('100.00');
        $this->groupPrice(88, 7, '6.00');

        $order = FileOrder::create([
            'price' => '10.00',
            'order_price' => '5.00',
            'profit' => '5.00',
            'user_id' => $user->id,
            'service_id' => 88,
            'request' => ['legacy' => true],
        ]);

        $this->assertSame('100.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame('10.00', number_format((float)$order->price, 2, '.', ''));
        $this->assertNull(data_get($order->request, 'file_group_pricing_applied'));
    }
}
