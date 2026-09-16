<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ImeiOrder;
use App\Models\ImeiService;
use App\Models\User;
use App\Services\Orders\OrderDispatchClaimService;
use App\Services\Orders\OrderDispatcher;
use App\Services\Orders\OrderSender;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ProviderFailureLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);
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

        Schema::create('api_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type')->default('dhru');
            $table->string('url')->nullable();
            $table->string('username')->nullable();
            $table->text('api_key')->nullable();
            $table->text('params')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('imei_services', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('remote_id')->nullable();
            $table->boolean('active')->default(true);
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
            $table->boolean('api_order')->default(true);
            $table->text('params')->nullable();
            $table->boolean('processing')->default(false);
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });
    }

    private function setupClaimedOrder(string $balance = '80.0000'): array
    {
        $user = User::create([
            'name' => 'Provider failure user',
            'email' => 'provider-failure@example.test',
            'username' => 'provider-failure',
            'balance' => $balance,
            'status' => 'active',
            'password' => 'test-password',
        ]);

        $provider = ApiProvider::create([
            'name' => 'Mock provider',
            'type' => 'dhru',
            'url' => 'https://provider.test',
            'username' => 'mock-user',
            'active' => 1,
        ]);

        $service = ImeiService::create([
            'name' => 'Mock IMEI service',
            'supplier_id' => $provider->id,
            'remote_id' => '101',
            'active' => 1,
        ]);

        $order = ImeiOrder::create([
            'device' => '356789012345678',
            'status' => 'inprogress',
            'processing' => true,
            'api_order' => true,
            'price' => '20.0000',
            'user_id' => $user->id,
            'service_id' => $service->id,
            'supplier_id' => $provider->id,
            'request' => [
                'charged_amount' => 20,
                'financial_state' => 'charged',
                'dispatch_claimed_at' => now()->toDateTimeString(),
            ],
            'response' => [],
        ]);

        return [$user, $provider, $service, $order];
    }

    private function dispatcherWithSender(OrderSender $sender): OrderDispatcher
    {
        $this->app->instance(OrderSender::class, $sender);
        return $this->app->make(OrderDispatcher::class);
    }

    public function test_timeout_returns_order_to_waiting_without_refund(): void
    {
        [$user, , , $order] = $this->setupClaimedOrder();

        $sender = Mockery::mock(OrderSender::class);
        $sender->shouldReceive('sendImei')->once()->andThrow(
            new \RuntimeException('cURL error 28: Operation timed out after 60000 milliseconds')
        );

        $this->dispatcherWithSender($sender)->send('imei', $order->id);

        $fresh = $order->fresh();
        $this->assertSame('waiting', $fresh->status);
        $this->assertFalse((bool)$fresh->processing);
        $this->assertSame('80.0000', number_format((float)$user->fresh()->balance, 4, '.', ''));
        $this->assertSame('charged', data_get($fresh->request, 'financial_state'));
        $this->assertSame('Waiting', data_get($fresh->response, 'message'));
        $this->assertSame('TIMEOUT - Provider not responding', data_get($fresh->request, 'internal_dispatch_note'));
    }

    public function test_provider_low_balance_waits_without_refunding_customer(): void
    {
        [$user, , , $order] = $this->setupClaimedOrder();

        $sender = Mockery::mock(OrderSender::class);
        $sender->shouldReceive('sendImei')->once()->andThrow(
            new \RuntimeException('Provider balance is low - not enough balance')
        );

        $this->dispatcherWithSender($sender)->send('imei', $order->id);

        $fresh = $order->fresh();
        $this->assertSame('waiting', $fresh->status);
        $this->assertFalse((bool)$fresh->processing);
        $this->assertSame('80.0000', number_format((float)$user->fresh()->balance, 4, '.', ''));
        $this->assertSame('charged', data_get($fresh->request, 'financial_state'));
        $this->assertSame('Waiting', data_get($fresh->response, 'message'));
        $this->assertSame('NO ENOUGH BALANCE AT PROVIDER', data_get($fresh->request, 'internal_dispatch_note'));
    }

    public function test_explicit_provider_rejection_refunds_exactly_once(): void
    {
        [$user, , , $order] = $this->setupClaimedOrder();

        $sender = Mockery::mock(OrderSender::class);
        $sender->shouldReceive('sendImei')->once()->andReturn([
            'ok' => true,
            'retryable' => false,
            'status' => 'rejected',
            'remote_id' => 'REMOTE-REJECTED-1',
            'request' => ['action' => 'getimeiorder'],
            'response_raw' => ['STATUS' => 'Rejected'],
            'response_ui' => ['type' => 'error', 'message' => 'Rejected by provider'],
        ]);

        $dispatcher = $this->dispatcherWithSender($sender);
        $dispatcher->send('imei', $order->id);

        $fresh = $order->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertFalse((bool)$fresh->processing);
        $this->assertSame('100.0000', number_format((float)$user->fresh()->balance, 4, '.', ''));
        $this->assertSame('refunded', data_get($fresh->request, 'financial_state'));
        $this->assertSame('Rejected', data_get($fresh->response, 'message'));
        $this->assertSame('Rejected by provider', data_get($fresh->request, 'internal_provider_note'));

        // A repeated save/status observation must not refund the same order twice.
        $fresh->comments = 'Reviewed after rejection';
        $fresh->save();
        $this->assertSame('100.0000', number_format((float)$user->fresh()->balance, 4, '.', ''));
    }

    public function test_authentication_failure_waits_for_automatic_retry_without_refund(): void
    {
        [$user, , , $order] = $this->setupClaimedOrder();

        $sender = Mockery::mock(OrderSender::class);
        $sender->shouldReceive('sendImei')->once()->andThrow(
            new \RuntimeException('HTTP 401 Unauthorized: invalid API key')
        );

        $this->dispatcherWithSender($sender)->send('imei', $order->id);

        $fresh = $order->fresh();
        $this->assertSame('waiting', $fresh->status);
        $this->assertFalse((bool)$fresh->processing);
        $this->assertSame('80.0000', number_format((float)$user->fresh()->balance, 4, '.', ''));
        $this->assertSame('charged', data_get($fresh->request, 'financial_state'));
        $this->assertSame('Waiting', data_get($fresh->response, 'message'));
        $this->assertSame('AUTH FAILED - Check username/api_key/auth_mode', data_get($fresh->request, 'internal_dispatch_note'));

        $adminView = view('admin.orders.modals.view', ['row' => $fresh, 'kind' => 'imei'])->render();
        $this->assertStringContainsString('Why this order is waiting', $adminView);
        $this->assertStringContainsString('AUTH FAILED - Check username/api_key/auth_mode', $adminView);

        $claimed = app(OrderDispatchClaimService::class)->claim(ImeiOrder::class, $fresh->id);
        $this->assertNotNull($claimed);

        $recoveredSender = Mockery::mock(OrderSender::class);
        $recoveredSender->shouldReceive('sendImei')->once()->andReturn([
            'ok' => true,
            'status' => 'inprogress',
            'remote_id' => 'REMOTE-AFTER-FIX-1',
            'response_ui' => ['type' => 'info', 'message' => 'In Progress'],
        ]);
        $this->dispatcherWithSender($recoveredSender)->send('imei', $fresh->id);

        $retried = $fresh->fresh();
        $this->assertSame('inprogress', $retried->status);
        $this->assertSame('REMOTE-AFTER-FIX-1', $retried->remote_id);
        $this->assertNull(data_get($retried->request, 'internal_dispatch_note'));
    }

    public function test_http_503_is_retryable_and_does_not_refund(): void
    {
        [$user, , , $order] = $this->setupClaimedOrder();

        $sender = Mockery::mock(OrderSender::class);
        $sender->shouldReceive('sendImei')->once()->andThrow(
            new \RuntimeException('HTTP 503 Service Unavailable')
        );

        $this->dispatcherWithSender($sender)->send('imei', $order->id);

        $fresh = $order->fresh();
        $this->assertSame('waiting', $fresh->status);
        $this->assertFalse((bool)$fresh->processing);
        $this->assertSame('80.0000', number_format((float)$user->fresh()->balance, 4, '.', ''));
        $this->assertSame('charged', data_get($fresh->request, 'financial_state'));
        $this->assertSame('Waiting', data_get($fresh->response, 'message'));
        $this->assertSame('PROVIDER MAINTENANCE / DOWN', data_get($fresh->request, 'internal_dispatch_note'));
    }

    public function test_failed_gateway_result_waits_even_when_gateway_labels_it_rejected(): void
    {
        [$user, , , $order] = $this->setupClaimedOrder();

        $sender = Mockery::mock(OrderSender::class);
        $sender->shouldReceive('sendImei')->once()->andReturn([
            'ok' => false,
            'retryable' => false,
            'status' => 'rejected',
            'response_ui' => ['type' => 'error', 'message' => 'Invalid API key'],
            'response_raw' => ['ERROR' => [['MESSAGE' => 'Invalid API key']]],
        ]);

        $this->dispatcherWithSender($sender)->send('imei', $order->id);

        $fresh = $order->fresh();
        $this->assertSame('waiting', $fresh->status);
        $this->assertSame('80.0000', number_format((float)$user->fresh()->balance, 4, '.', ''));
        $this->assertSame('Waiting', data_get($fresh->response, 'message'));
        $this->assertSame('AUTH FAILED - Check username/api_key/auth_mode', data_get($fresh->request, 'internal_dispatch_note'));
    }
}
