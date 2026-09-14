<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class OrderProviderReferenceAuditTest extends TestCase
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

        foreach (['imei_orders', 'server_orders', 'file_orders', 'smm_orders'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('status')->default('waiting');
                $table->boolean('api_order')->default(false);
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->string('remote_id')->nullable();
                $table->unsignedBigInteger('service_id')->nullable();
                $table->boolean('processing')->default(false);
            });
        }
    }

    public function test_clean_active_provider_references_pass(): void
    {
        DB::table('api_providers')->insert(['id' => 7, 'active' => 1]);
        DB::table('imei_orders')->insert([
            'id' => 1,
            'status' => 'inprogress',
            'api_order' => 1,
            'supplier_id' => 7,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('orders:provider-reference-audit', [], $output);
        $text = $output->fetch();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('active_order_provider_missing', $text);
    }

    public function test_actionable_broken_provider_references_fail(): void
    {
        DB::table('api_providers')->insert([
            ['id' => 7, 'active' => 0],
        ]);

        DB::table('imei_orders')->insert([
            'id' => 1,
            'status' => 'waiting',
            'api_order' => 1,
            'supplier_id' => null,
        ]);

        DB::table('server_orders')->insert([
            'id' => 2,
            'status' => 'inprogress',
            'api_order' => 1,
            'supplier_id' => 999,
        ]);

        DB::table('file_orders')->insert([
            'id' => 3,
            'status' => 'waiting',
            'api_order' => 1,
            'supplier_id' => 7,
        ]);

        DB::table('smm_orders')->insert([
            'id' => 4,
            'status' => 'waiting',
            'api_order' => 0,
            'supplier_id' => 7,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('orders:provider-reference-audit', [], $output);
        $text = $output->fetch();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('active_api_order_without_supplier', $text);
        $this->assertStringContainsString('active_order_provider_missing', $text);
        $this->assertStringContainsString('active_order_provider_disabled', $text);
        $this->assertStringContainsString('manual_waiting_order_with_supplier', $text);
    }

    public function test_historical_missing_provider_is_informational_only(): void
    {
        DB::table('server_orders')->insert([
            'id' => 9,
            'status' => 'success',
            'api_order' => 1,
            'supplier_id' => 999,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('orders:provider-reference-audit', [], $output);
        $text = $output->fetch();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('historical_order_provider_missing', $text);
    }
}
