<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class CustomFieldAuditTest extends TestCase
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
            });
        }

        Schema::create('custom_fields', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->string('service_type', 50);
            $table->string('input', 255)->nullable();
            $table->unsignedInteger('minimum')->default(0);
            $table->unsignedInteger('maximum')->default(0);
            $table->boolean('required')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('ordering')->default(0);
        });
    }

    public function test_valid_custom_field_passes_audit(): void
    {
        DB::table('smm_services')->insert(['id' => 5]);
        DB::table('custom_fields')->insert([
            'service_id' => 5,
            'service_type' => 'smm_service',
            'input' => 'link',
            'minimum' => 1,
            'maximum' => 255,
            'required' => 1,
            'active' => 1,
            'ordering' => 1,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('services:custom-field-audit', [], $output);
        $text = $output->fetch();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('missing_service', $text);
    }

    public function test_broken_references_and_duplicate_inputs_fail_audit(): void
    {
        DB::table('imei_services')->insert(['id' => 9]);

        DB::table('custom_fields')->insert([
            [
                'service_id' => 999,
                'service_type' => 'imei_service',
                'input' => 'imei',
                'minimum' => 0,
                'maximum' => 0,
                'required' => 1,
                'active' => 1,
                'ordering' => 1,
            ],
            [
                'service_id' => 9,
                'service_type' => 'imei_service',
                'input' => 'email',
                'minimum' => 10,
                'maximum' => 5,
                'required' => 0,
                'active' => 1,
                'ordering' => 2,
            ],
            [
                'service_id' => 9,
                'service_type' => 'imei_service',
                'input' => 'EMAIL',
                'minimum' => 0,
                'maximum' => 0,
                'required' => 0,
                'active' => 1,
                'ordering' => 3,
            ],
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('services:custom-field-audit', [], $output);
        $text = $output->fetch();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('missing_service', $text);
        $this->assertStringContainsString('minimum_over_maximum', $text);
        $this->assertStringContainsString('duplicate_input_per_service', $text);
    }
}
