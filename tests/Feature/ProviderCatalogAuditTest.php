<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class ProviderCatalogAuditTest extends TestCase
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
            $table->string('name')->nullable();
        });

        foreach (['imei', 'server', 'file'] as $kind) {
            Schema::create('remote_' . $kind . '_services', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('api_provider_id');
                $table->string('remote_id')->nullable();
                $table->string('name')->nullable();
                $table->decimal('price', 12, 4)->default(0);
            });
        }

        Schema::create('remote_smm_services', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('api_provider_id');
            $table->string('remote_id')->nullable();
            $table->string('name')->nullable();
            $table->decimal('price', 12, 4)->default(0);
            $table->integer('min')->default(0);
            $table->integer('max')->default(0);
        });
    }

    public function test_valid_remote_catalog_passes(): void
    {
        DB::table('api_providers')->insert(['id' => 1, 'name' => 'Provider']);
        DB::table('remote_server_services')->insert([
            'api_provider_id' => 1,
            'remote_id' => '115',
            'name' => 'Service',
            'price' => 9.25,
        ]);
        DB::table('remote_smm_services')->insert([
            'api_provider_id' => 1,
            'remote_id' => '3148',
            'name' => 'SMM',
            'price' => 1.225,
            'min' => 100,
            'max' => 10000,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('providers:catalog-audit', [], $output);
        $text = $output->fetch();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('provider_missing', $text);
        $this->assertStringContainsString('duplicate_provider_remote_id', $text);
    }

    public function test_broken_catalog_rows_fail(): void
    {
        DB::table('api_providers')->insert(['id' => 1, 'name' => 'Provider']);

        DB::table('remote_imei_services')->insert([
            ['api_provider_id' => 99, 'remote_id' => 'X', 'name' => 'Missing provider', 'price' => 1],
            ['api_provider_id' => 1, 'remote_id' => '', 'name' => 'Empty ID', 'price' => -1],
        ]);

        DB::table('remote_server_services')->insert([
            ['api_provider_id' => 1, 'remote_id' => 'DUP', 'name' => 'One', 'price' => 2],
            ['api_provider_id' => 1, 'remote_id' => 'DUP', 'name' => 'Two', 'price' => 3],
        ]);

        DB::table('remote_smm_services')->insert([
            'api_provider_id' => 1,
            'remote_id' => 'SMM-BAD',
            'name' => 'Bad range',
            'price' => 1,
            'min' => 5000,
            'max' => 1000,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('providers:catalog-audit', [], $output);
        $text = $output->fetch();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('empty_remote_id', $text);
        $this->assertStringContainsString('provider_missing', $text);
        $this->assertStringContainsString('negative_price', $text);
        $this->assertStringContainsString('duplicate_provider_remote_id', $text);
        $this->assertStringContainsString('smm_min_over_max', $text);
    }
}
