<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServiceRoutingAuditTest extends TestCase
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
            $table->boolean('active')->default(true);
        });

        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            Schema::create($kind . '_services', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->unsignedInteger('source')->default(1);
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->string('remote_id')->nullable();
            });

            Schema::create($kind . '_orders', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('service_id')->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->string('remote_id')->nullable();
                $table->boolean('api_order')->default(false);
                $table->string('status')->default('waiting');
                $table->boolean('processing')->default(false);
            });
        }
    }

    public function test_clean_manual_and_api_routes_pass(): void
    {
        DB::table('api_providers')->insert(['id' => 7, 'active' => 1]);

        DB::table('server_services')->insert([
            ['id' => 1, 'name' => 'Manual', 'source' => 1, 'active' => 1, 'supplier_id' => null, 'remote_id' => null],
            ['id' => 2, 'name' => 'API', 'source' => 2, 'active' => 1, 'supplier_id' => 7, 'remote_id' => 'ABC'],
        ]);

        DB::table('server_orders')->insert([
            ['id' => 1, 'service_id' => 1, 'supplier_id' => null, 'remote_id' => null, 'api_order' => 0, 'status' => 'waiting', 'processing' => 0],
            ['id' => 2, 'service_id' => 2, 'supplier_id' => 7, 'remote_id' => null, 'api_order' => 1, 'status' => 'waiting', 'processing' => 0],
        ]);

        $exit = Artisan::call('services:routing-audit');

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('manual_service_with_provider_link', $out);
        $this->assertStringContainsString('waiting_api_order_without_remote_service', $out);
    }

    public function test_broken_manual_and_api_routes_fail_and_are_reported(): void
    {
        DB::table('api_providers')->insert(['id' => 7, 'active' => 1]);

        DB::table('server_services')->insert([
            ['id' => 10, 'name' => 'Broken manual', 'source' => 1, 'active' => 1, 'supplier_id' => 7, 'remote_id' => 'OLD'],
            ['id' => 11, 'name' => 'Broken API', 'source' => 2, 'active' => 1, 'supplier_id' => null, 'remote_id' => null],
        ]);

        DB::table('server_orders')->insert([
            ['id' => 20, 'service_id' => 10, 'supplier_id' => 7, 'remote_id' => null, 'api_order' => 1, 'status' => 'waiting', 'processing' => 0],
            ['id' => 21, 'service_id' => 11, 'supplier_id' => null, 'remote_id' => null, 'api_order' => 1, 'status' => 'waiting', 'processing' => 0],
        ]);

        $exit = Artisan::call('services:routing-audit --details=20');

        $this->assertSame(1, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('manual_service_with_provider_link', $out);
        $this->assertStringContainsString('api_service_missing_provider', $out);
        $this->assertStringContainsString('api_service_missing_remote_id', $out);
        $this->assertStringContainsString('api_order_on_manual_service', $out);
        $this->assertStringContainsString('waiting_api_order_without_provider', $out);
        $this->assertStringContainsString('waiting_api_order_without_remote_service', $out);
    }
}
