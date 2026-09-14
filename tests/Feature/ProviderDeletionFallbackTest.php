<?php

namespace Tests\Feature;

use App\Exceptions\ProviderHasActiveOrdersException;
use App\Models\ApiProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProviderDeletionFallbackTest extends TestCase
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

    public function test_provider_delete_converts_linked_service_to_manual_and_preserves_pricing(): void
    {
        $provider = ApiProvider::create([
            'name' => 'Disposable provider',
            'type' => 'webx',
            'active' => 1,
        ]);

        $serviceId = DB::table('server_services')->insertGetId([
            'name' => json_encode(['en' => 'Historical service']),
            'cost' => 12.3456,
            'profit' => 4.5000,
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

        DB::table('server_orders')->insert([
            'service_id' => $serviceId,
            'supplier_id' => $provider->id,
            'remote_id' => 'ORDER-1',
            'status' => 'success',
            'api_order' => 1,
            'processing' => 0,
        ]);

        $provider->delete();

        $this->assertDatabaseMissing('api_providers', ['id' => $provider->id]);

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
        $this->assertSame('provider_deleted', $params['provider_manual_fallback']['reason']);
    }

    public function test_provider_delete_is_blocked_while_active_order_depends_on_it(): void
    {
        $provider = ApiProvider::create([
            'name' => 'Busy provider',
            'type' => 'webx',
            'active' => 1,
        ]);

        $serviceId = DB::table('server_services')->insertGetId([
            'name' => json_encode(['en' => 'Busy service']),
            'source' => 2,
            'supplier_id' => $provider->id,
            'remote_id' => '145',
            'active' => 1,
        ]);

        DB::table('server_orders')->insert([
            'service_id' => $serviceId,
            'supplier_id' => $provider->id,
            'remote_id' => 'ORDER-2',
            'status' => 'inprogress',
            'api_order' => 1,
            'processing' => 1,
        ]);

        try {
            $provider->delete();
            $this->fail('Expected ProviderHasActiveOrdersException was not thrown.');
        } catch (ProviderHasActiveOrdersException $e) {
            $this->assertSame((int)$provider->id, $e->providerId);
            $this->assertSame(1, $e->activeOrderCount);
        }

        $this->assertDatabaseHas('api_providers', ['id' => $provider->id]);
        $this->assertDatabaseHas('server_services', [
            'id' => $serviceId,
            'supplier_id' => $provider->id,
            'remote_id' => '145',
        ]);
    }
}
