<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class ServiceIdentityAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        foreach (['imei','server','file','smm'] as $kind) {
            Schema::create($kind . '_services', function (Blueprint $table): void {
                $table->id();
                $table->string('alias')->nullable();
                $table->integer('source')->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->string('remote_id')->nullable();
            });
        }
    }

    public function test_clean_service_identity_passes(): void
    {
        DB::table('imei_services')->insert([
            'alias' => 'clean-service',
            'source' => 2,
            'supplier_id' => 7,
            'remote_id' => '123',
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('services:identity-audit', [], $output);
        $text = $output->fetch();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('duplicate_alias', $text);
        $this->assertStringContainsString('duplicate_provider_remote_mapping', $text);
    }

    public function test_duplicate_alias_invalid_source_and_duplicate_mapping_fail(): void
    {
        DB::table('server_services')->insert([
            ['alias' => 'Same-Alias', 'source' => 2, 'supplier_id' => 8, 'remote_id' => '501'],
            ['alias' => 'same-alias', 'source' => 9, 'supplier_id' => 8, 'remote_id' => '501'],
            ['alias' => '', 'source' => 1, 'supplier_id' => null, 'remote_id' => null],
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('services:identity-audit', [], $output);
        $text = $output->fetch();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('empty_alias', $text);
        $this->assertStringContainsString('duplicate_alias', $text);
        $this->assertStringContainsString('invalid_source', $text);
        $this->assertStringContainsString('duplicate_provider_remote_mapping', $text);
    }
}
