<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ImeiOrder;
use App\Services\Orders\DhruOrderGateway;
use App\Services\Orders\UnlockbaseOrderGateway;
use App\Services\Orders\WebxOrderGateway;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SyncImeiTransientErrorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('api_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('type')->default('dhru');
            $table->string('url')->nullable();
            $table->string('username')->nullable();
            $table->text('api_key')->nullable();
            $table->text('params')->nullable();
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
            $table->boolean('api_order')->default(false);
            $table->text('params')->nullable();
            $table->boolean('processing')->default(false);
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_dhru_status_check_error_keeps_remote_order_inprogress_and_does_not_reject(): void
    {
        $provider = ApiProvider::create([
            'name' => 'Transient DHRU',
            'type' => 'dhru',
            'url' => 'https://example.test',
            'active' => true,
        ]);

        $order = ImeiOrder::create([
            'remote_id' => 'REMOTE-55',
            'status' => 'inprogress',
            'api_order' => true,
            'processing' => true,
            'supplier_id' => $provider->id,
            'request' => [],
            'response' => [],
        ]);

        $dhru = Mockery::mock(DhruOrderGateway::class);
        $dhru->shouldReceive('getImeiOrder')->once()->andReturn([
            'response_raw' => [
                'ERROR' => [[
                    'MESSAGE' => 'Temporary provider error',
                ]],
            ],
        ]);

        $webx = Mockery::mock(WebxOrderGateway::class);
        $unlockbase = Mockery::mock(UnlockbaseOrderGateway::class);

        $this->app->instance(DhruOrderGateway::class, $dhru);
        $this->app->instance(WebxOrderGateway::class, $webx);
        $this->app->instance(UnlockbaseOrderGateway::class, $unlockbase);

        $this->artisan('orders:sync-imei', ['--only-id' => $order->id])
            ->assertSuccessful();

        $fresh = $order->fresh();
        $this->assertSame('inprogress', $fresh->status);
        $this->assertTrue((bool)$fresh->processing);
        $this->assertSame('Temporary provider error', data_get($fresh->response, 'message'));
    }
}
