<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Orders\ImeiOrdersController;
use App\Models\ImeiOrder;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminOrderStatusTransitionSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
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

        Route::middleware('web')->post('/_status-safety/orders/{id}', [ImeiOrdersController::class, 'update']);
    }

    private function user(string $balance = '80.0000'): User
    {
        return User::create([
            'name' => 'Status safety user',
            'email' => 'status-safety@example.test',
            'username' => 'status-safety',
            'balance' => $balance,
            'status' => 'active',
            'password' => 'test-password',
        ]);
    }

    private function order(User $user, string $status = 'waiting'): ImeiOrder
    {
        return ImeiOrder::create([
            'status' => $status,
            'price' => '20.0000',
            'user_id' => $user->id,
            'request' => ['charged_amount' => 20, 'financial_state' => 'charged'],
            'response' => [],
            'processing' => $status === 'inprogress',
        ]);
    }

    public function test_manual_inprogress_without_remote_id_is_refused_without_financial_change(): void
    {
        $user = $this->user();
        $order = $this->order($user, 'waiting');

        $this->from('/_status-safety/back')
            ->post("/_status-safety/orders/{$order->id}", ['status' => 'inprogress'])
            ->assertRedirect('/_status-safety/back')
            ->assertSessionHasErrors('status');

        $fresh = $order->fresh();
        $this->assertSame('waiting', $fresh->status);
        $this->assertFalse((bool)$fresh->processing);
        $this->assertSame('80.0000', number_format((float)$user->fresh()->balance, 4, '.', ''));
        $this->assertSame('charged', data_get($fresh->request, 'financial_state'));
    }

    public function test_manual_waiting_without_remote_id_clears_processing(): void
    {
        $user = $this->user();
        $order = $this->order($user, 'waiting');
        DB::table('imei_orders')->where('id', $order->id)->update(['processing' => 1]);

        $this->post("/_status-safety/orders/{$order->id}", ['status' => 'waiting'])
            ->assertRedirect(route('admin.orders.imei.index'));

        $fresh = $order->fresh();
        $this->assertSame('waiting', $fresh->status);
        $this->assertFalse((bool)$fresh->processing);
    }

    public function test_manual_waiting_for_remote_order_is_normalized_to_inprogress(): void
    {
        $user = $this->user();
        $order = $this->order($user, 'inprogress');
        $order->remote_id = 'REMOTE-200';
        $order->processing = true;
        $order->save();

        $this->post("/_status-safety/orders/{$order->id}", ['status' => 'waiting'])
            ->assertRedirect(route('admin.orders.imei.index'));

        $fresh = $order->fresh();
        $this->assertSame('inprogress', $fresh->status);
        $this->assertTrue((bool)$fresh->processing);
        $this->assertSame('80.0000', number_format((float)$user->fresh()->balance, 4, '.', ''));
    }

    public function test_repeated_terminal_edit_does_not_rewrite_reply_timestamp(): void
    {
        $user = $this->user();
        $order = $this->order($user, 'success');
        $original = now()->subHour()->startOfSecond();
        $order->replied_at = $original;
        $order->save();

        $this->post("/_status-safety/orders/{$order->id}", [
            'status' => 'success',
            'comments' => 'Updated note only',
        ])->assertRedirect(route('admin.orders.imei.index'));

        $this->assertSame($original->toDateTimeString(), $order->fresh()->replied_at?->toDateTimeString());
    }
}
