<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Orders\ImeiOrdersController;
use App\Http\Controllers\Admin\UserFinanceController;
use App\Models\FinanceAccount;
use App\Models\ImeiOrder;
use App\Models\User;
use App\Services\Orders\OrderFinanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderFinanceIntegrityTest extends TestCase
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
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('status')->default('active');
            $table->string('password')->default('test');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('finance_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('locked_amount', 12, 2)->default(0);
            $table->decimal('total_receipts', 12, 2)->default(0);
            $table->decimal('paid_credits', 12, 2)->default(0);
            $table->decimal('overdraft_limit', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('finance_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('kind');
            $table->string('direction');
            $table->boolean('paid')->default(false);
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('imei_orders', function (Blueprint $table): void {
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

        Route::middleware('web')->get('/_finance-test/users/{user}/summary', [UserFinanceController::class, 'summary']);
        Route::middleware('web')->post('/_finance-test/users/{user}/credits', [UserFinanceController::class, 'addRemoveCredits']);
        Route::middleware('web')->post('/_finance-test/users/{user}/payment', [UserFinanceController::class, 'addPayment']);
        Route::middleware('web')->post('/_finance-test/users/{user}/overdraft', [UserFinanceController::class, 'setOverdraft']);
        Route::middleware('web')->post('/_finance-test/orders/{id}', [ImeiOrdersController::class, 'update']);
    }

    private function user(string $balance = '100.00'): User
    {
        return User::create([
            'name' => 'Finance test user',
            'email' => 'finance@example.test',
            'username' => 'finance-test',
            'balance' => $balance,
            'status' => 'active',
            'password' => 'test-password',
        ]);
    }

    private function account(User $user, string $total = '1000.00', string $paid = '1000.00', string $overdraft = '0.00'): FinanceAccount
    {
        return FinanceAccount::create([
            'user_id' => $user->id,
            'locked_amount' => '0.00',
            'total_receipts' => $total,
            'paid_credits' => $paid,
            'overdraft_limit' => $overdraft,
        ]);
    }

    private function order(User $user, string $status = 'success', string $state = 'charged', string $amount = '20.00'): ImeiOrder
    {
        $request = ['charged_amount' => (float)$amount, 'financial_state' => $state];
        if ($state === 'refunded') {
            $request['refunded_at'] = now()->toDateTimeString();
            $request['refunded_amount'] = (float)$amount;
        }

        return ImeiOrder::create([
            'status' => $status,
            'price' => $amount,
            'user_id' => $user->id,
            'request' => $request,
            'response' => [],
            'processing' => false,
        ]);
    }

    public function test_finance_summary_never_replaces_spendable_balance_with_historical_paid_credits(): void
    {
        $user = $this->user('75.00');
        $this->account($user, '1000.00', '1000.00');

        $this->getJson("/_finance-test/users/{$user->id}/summary")
            ->assertOk()->assertJsonPath('balance', '75.00')->assertJsonPath('available', '75.00');

        $this->assertSame('75.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
    }

    public function test_paid_credit_and_payment_add_to_current_balance_without_resetting_order_spend(): void
    {
        $user = $this->user('75.00');
        $this->account($user, '100.00', '80.00');

        $this->postJson("/_finance-test/users/{$user->id}/credits", [
            'action' => 'add', 'amount' => 10, 'paid' => true,
        ])->assertOk();
        $this->assertSame('85.00', number_format((float)$user->fresh()->balance, 2, '.', ''));

        $this->postJson("/_finance-test/users/{$user->id}/payment", ['amount' => 10])->assertOk();
        $this->assertSame('95.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
    }

    public function test_unpaid_credit_does_not_change_spendable_balance(): void
    {
        $user = $this->user('75.00');
        $this->account($user, '100.00', '80.00');

        $this->postJson("/_finance-test/users/{$user->id}/credits", [
            'action' => 'add', 'amount' => 25, 'paid' => false,
        ])->assertOk();

        $this->assertSame('75.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame('125.00', number_format((float)FinanceAccount::first()->total_receipts, 2, '.', ''));
    }

    public function test_overdraft_changes_balance_by_delta_and_cannot_write_off_used_credit(): void
    {
        $user = $this->user('30.00');
        $this->account($user, '100.00', '100.00', '20.00');

        $this->postJson("/_finance-test/users/{$user->id}/overdraft", ['overdraft' => 40])->assertOk();
        $this->assertSame('50.00', number_format((float)$user->fresh()->balance, 2, '.', ''));

        $this->postJson("/_finance-test/users/{$user->id}/overdraft", ['overdraft' => 0])->assertOk();
        $this->assertSame('10.00', number_format((float)$user->fresh()->balance, 2, '.', ''));

        $this->postJson("/_finance-test/users/{$user->id}/overdraft", ['overdraft' => 20])->assertOk();
        $user->forceFill(['balance' => '5.00'])->save();
        $this->postJson("/_finance-test/users/{$user->id}/overdraft", ['overdraft' => 0])->assertStatus(422);
        $this->assertSame('20.00', number_format((float)FinanceAccount::first()->overdraft_limit, 2, '.', ''));
    }

    public function test_two_stale_refund_attempts_credit_the_user_once(): void
    {
        $user = $this->user('80.00');
        $order = $this->order($user, 'rejected', 'charged', '20.00');
        $first = ImeiOrder::findOrFail($order->id);
        $second = ImeiOrder::findOrFail($order->id);
        $service = app(OrderFinanceService::class);

        $this->assertTrue($service->refundOrderIfNeeded($first, 'test_refund'));
        $this->assertFalse($service->refundOrderIfNeeded($second, 'test_refund_again'));
        $this->assertSame('100.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame('refunded', data_get($order->fresh()->request, 'financial_state'));
    }

    public function test_two_stale_recharge_attempts_debit_the_user_once(): void
    {
        $user = $this->user('100.00');
        $order = $this->order($user, 'rejected', 'refunded', '20.00');
        $first = ImeiOrder::findOrFail($order->id);
        $second = ImeiOrder::findOrFail($order->id);
        $service = app(OrderFinanceService::class);

        $this->assertTrue($service->rechargeOrderIfNeeded($first, 'retry'));
        $this->assertFalse($service->rechargeOrderIfNeeded($second, 'retry_again'));
        $this->assertSame('80.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame('charged', data_get($order->fresh()->request, 'financial_state'));
    }

    public function test_rejected_to_waiting_to_success_recharges_before_reactivation_and_never_twice(): void
    {
        $user = $this->user('100.00');
        $order = $this->order($user, 'rejected', 'refunded', '20.00');

        $this->post("/_finance-test/orders/{$order->id}", ['status' => 'waiting'])
            ->assertRedirect(route('admin.orders.imei.index'));
        $this->assertSame('80.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame('waiting', $order->fresh()->status);

        $this->post("/_finance-test/orders/{$order->id}", ['status' => 'success'])
            ->assertRedirect(route('admin.orders.imei.index'));
        $this->assertSame('80.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame('success', $order->fresh()->status);
    }

    public function test_reactivation_is_refused_when_refunded_amount_is_no_longer_available(): void
    {
        $user = $this->user('10.00');
        $order = $this->order($user, 'rejected', 'refunded', '20.00');

        $this->from('/_finance-test/back')
            ->post("/_finance-test/orders/{$order->id}", ['status' => 'waiting'])
            ->assertRedirect('/_finance-test/back')->assertSessionHasErrors('status');

        $this->assertSame('10.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame('rejected', $order->fresh()->status);
    }

    public function test_terminal_manual_status_refunds_once_even_when_submitted_twice(): void
    {
        $user = $this->user('80.00');
        $order = $this->order($user, 'success', 'charged', '20.00');

        $this->post("/_finance-test/orders/{$order->id}", ['status' => 'rejected']);
        $this->post("/_finance-test/orders/{$order->id}", ['status' => 'rejected']);

        $this->assertSame('100.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame('refunded', data_get($order->fresh()->request, 'financial_state'));
    }

    public function test_admin_can_replace_or_clear_a_success_reply_without_changing_finances(): void
    {
        $user = $this->user('80.00');
        $order = $this->order($user, 'success', 'charged', '20.00');

        $this->post("/_finance-test/orders/{$order->id}", [
            'status' => 'success', 'provider_reply_html' => '<p>First reply</p>',
        ])->assertRedirect(route('admin.orders.imei.index'));
        $this->assertSame('<p>First reply</p>', data_get($order->fresh()->response, 'provider_reply_html'));

        $this->post("/_finance-test/orders/{$order->id}", [
            'status' => 'success', 'provider_reply_html' => '<p>Corrected reply</p>',
        ])->assertRedirect(route('admin.orders.imei.index'));
        $this->assertSame('<p>Corrected reply</p>', data_get($order->fresh()->response, 'provider_reply_html'));

        $this->post("/_finance-test/orders/{$order->id}", [
            'status' => 'success', 'provider_reply_html' => '',
        ])->assertRedirect(route('admin.orders.imei.index'));
        $this->assertSame('', data_get($order->fresh()->response, 'provider_reply_html'));
        $this->assertSame('80.00', number_format((float)$user->fresh()->balance, 2, '.', ''));
        $this->assertSame('charged', data_get($order->fresh()->request, 'financial_state'));
    }

    public function test_audit_reports_financial_state_counts_without_identifiers_or_secret_payloads(): void
    {
        $user = $this->user('100.00');
        $this->order($user, 'success', 'refunded', '20.00');
        $this->order($user, 'rejected', 'charged', '15.00');

        $exit = Artisan::call('orders:finance-audit', ['--json' => true]);
        $output = trim(Artisan::output());
        $counts = json_decode($output, true);

        $this->assertIsArray($counts, $output);
        $this->assertSame(1, $counts['active_or_success_refunded'] ?? null, $output);
        $this->assertSame(1, $counts['rejected_or_cancelled_not_refunded'] ?? null, $output);
        $this->assertSame(1, $exit, $output);
        $this->assertStringNotContainsString('finance@example.test', $output);
    }
}
