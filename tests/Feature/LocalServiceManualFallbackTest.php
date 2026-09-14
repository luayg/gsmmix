<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Services\Providers\LocalServiceManualFallback;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocalServiceManualFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('api_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type')->default('webx');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('server_services', function (Blueprint $table): void {
            $table->id();
            $table->text('name')->nullable();
            $table->decimal('cost', 12, 4)->default(0);
            $table->decimal('profit', 12, 4)->default(0);
            $table->unsignedTinyInteger('profit_type')->default(1);
            $table->boolean('active')->default(true);
            $table->unsignedTinyInteger('source')->default(2);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('remote_id')->nullable();
            $table->boolean('use_remote_cost')->default(false);
            $table->boolean('use_remote_price')->default(false);
            $table->boolean('stop_on_api_change')->default(false);
            $table->text('params')->nullable();
            $table->timestamps();
        });

        Schema::create('remote_server_services', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('api_provider_id');
            $table->string('remote_id');
            $table->string('name')->nullable();
            $table->decimal('price', 12, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('server_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('remote_id')->nullable();
            $table->string('status')->default('waiting');
            $table->boolean('api_order')->default(true);
            $table->boolean('processing')->default(false);
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();
        });
    }

    public function test_removed_remote_service_becomes_manual_without_losing_local_pricing(): void
    {
        $provider = ApiProvider::create([
            'name' => 'WebX provider',
            'type' => 'webx',
            'active' => 1,
        ]);

        DB::table('remote_server_services')->insert([
            ['api_provider_id' => $provider->id, 'remote_id' => '145', 'name' => 'Still available', 'price' => 18.4],
            ['api_provider_id' => $provider->id, 'remote_id' => '146', 'name' => 'Still available 2', 'price' => 40.4],
        ]);

        $serviceId = DB::table('server_services')->insertGetId([
            'name' => json_encode(['en' => 'Removed service']),
            'cost' => 12.3456,
            'profit' => 4.5,
            'profit_type' => 1,
            'active' => 1,
            'source' => 2,
            'supplier_id' => $provider->id,
            'remote_id' => '115',
            'use_remote_cost' => 1,
            'use_remote_price' => 1,
            'stop_on_api_change' => 1,
            'params' => json_encode(['keep_me' => 'yes']),
        ]);

        $orderId = DB::table('server_orders')->insertGetId([
            'service_id' => $serviceId,
            'supplier_id' => $provider->id,
            'status' => 'waiting',
            'api_order' => 1,
            'processing' => 0,
            'request' => json_encode(['charged_amount' => 20]),
            'response' => json_encode([]),
        ]);

        $result = app(LocalServiceManualFallback::class)->reconcile($provider, 'server');

        $this->assertSame(1, $result['converted_services']);
        $this->assertSame(1, $result['converted_orders']);

        $service = DB::table('server_services')->where('id', $serviceId)->first();
        $this->assertSame(1, (int)$service->active);
        $this->assertSame(1, (int)$service->source);
        $this->assertNull($service->supplier_id);
        $this->assertNull($service->remote_id);
        $this->assertSame('12.3456', number_format((float)$service->cost, 4, '.', ''));
        $this->assertSame('4.5000', number_format((float)$service->profit, 4, '.', ''));
        $this->assertSame(0, (int)$service->use_remote_cost);
        $this->assertSame(0, (int)$service->use_remote_price);
        $this->assertSame(0, (int)$service->stop_on_api_change);

        $params = json_decode((string)$service->params, true);
        $this->assertSame('yes', $params['keep_me']);
        $this->assertSame('115', $params['provider_manual_fallback']['remote_id']);
        $this->assertSame('remote_service_removed', $params['provider_manual_fallback']['reason']);

        $order = DB::table('server_orders')->where('id', $orderId)->first();
        $this->assertSame(0, (int)$order->api_order);
        $this->assertSame(0, (int)$order->processing);
        $this->assertNull($order->supplier_id);
        $this->assertSame('waiting', $order->status);
        $this->assertSame('remote_service_removed', data_get(json_decode((string)$order->request, true), 'manual_fallback.reason'));
    }

    public function test_existing_remote_mapping_remains_api_linked(): void
    {
        $provider = ApiProvider::create([
            'name' => 'WebX provider',
            'type' => 'webx',
            'active' => 1,
        ]);

        DB::table('remote_server_services')->insert([
            'api_provider_id' => $provider->id,
            'remote_id' => '145',
            'name' => 'Available service',
            'price' => 18.4,
        ]);

        $serviceId = DB::table('server_services')->insertGetId([
            'name' => json_encode(['en' => 'Available service']),
            'source' => 2,
            'supplier_id' => $provider->id,
            'remote_id' => '145',
            'active' => 1,
        ]);

        $result = app(LocalServiceManualFallback::class)->reconcile($provider, 'server');

        $this->assertSame(0, $result['converted_services']);
        $service = DB::table('server_services')->where('id', $serviceId)->first();
        $this->assertSame((int)$provider->id, (int)$service->supplier_id);
        $this->assertSame('145', $service->remote_id);
        $this->assertSame(2, (int)$service->source);
    }

    public function test_empty_remote_catalog_never_mass_converts_local_services(): void
    {
        $provider = ApiProvider::create([
            'name' => 'Temporary empty provider',
            'type' => 'webx',
            'active' => 1,
        ]);

        $serviceId = DB::table('server_services')->insertGetId([
            'name' => json_encode(['en' => 'Keep API link']),
            'source' => 2,
            'supplier_id' => $provider->id,
            'remote_id' => '115',
            'active' => 1,
        ]);

        $result = app(LocalServiceManualFallback::class)->reconcile($provider, 'server');

        $this->assertTrue($result['skipped_empty_catalog']);
        $this->assertSame(0, $result['converted_services']);

        $service = DB::table('server_services')->where('id', $serviceId)->first();
        $this->assertSame((int)$provider->id, (int)$service->supplier_id);
        $this->assertSame('115', $service->remote_id);
        $this->assertSame(2, (int)$service->source);
    }

    public function test_inprogress_or_already_sent_orders_are_not_rewritten(): void
    {
        $provider = ApiProvider::create([
            'name' => 'WebX provider',
            'type' => 'webx',
            'active' => 1,
        ]);

        DB::table('remote_server_services')->insert([
            'api_provider_id' => $provider->id,
            'remote_id' => '145',
            'name' => 'Still available',
            'price' => 18.4,
        ]);

        $serviceId = DB::table('server_services')->insertGetId([
            'name' => json_encode(['en' => 'Removed service']),
            'source' => 2,
            'supplier_id' => $provider->id,
            'remote_id' => '115',
            'active' => 1,
        ]);

        $inprogressId = DB::table('server_orders')->insertGetId([
            'service_id' => $serviceId,
            'supplier_id' => $provider->id,
            'status' => 'inprogress',
            'api_order' => 1,
            'processing' => 1,
            'request' => json_encode([]),
            'response' => json_encode([]),
        ]);

        $sentId = DB::table('server_orders')->insertGetId([
            'service_id' => $serviceId,
            'supplier_id' => $provider->id,
            'remote_id' => 'ORDER-123',
            'status' => 'inprogress',
            'api_order' => 1,
            'processing' => 0,
            'request' => json_encode([]),
            'response' => json_encode([]),
        ]);

        $result = app(LocalServiceManualFallback::class)->reconcile($provider, 'server');

        $this->assertSame(1, $result['converted_services']);
        $this->assertSame(0, $result['converted_orders']);
        $this->assertSame(1, (int)DB::table('server_orders')->where('id', $inprogressId)->value('api_order'));
        $this->assertSame(1, (int)DB::table('server_orders')->where('id', $sentId)->value('api_order'));
    }
}
