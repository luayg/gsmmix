<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class ServiceGroupPriceAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            Schema::create($kind . '_services', function (Blueprint $table): void {
                $table->id();
                $table->decimal('cost', 12, 4)->default(0);
                $table->decimal('profit', 12, 4)->default(0);
                $table->integer('profit_type')->default(1);
            });
        }

        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('service_group_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->string('service_type');
            $table->unsignedBigInteger('group_id');
            $table->decimal('price', 12, 4)->default(0);
            $table->boolean('auto_price')->default(false);
            $table->decimal('discount', 12, 4)->default(0);
            $table->tinyInteger('discount_type')->default(1);
        });
    }

    public function test_group_price_audit_passes_for_valid_rows(): void
    {
        DB::table('smm_services')->insert(['id' => 10]);
        DB::table('groups')->insert(['id' => 3, 'name' => 'VIP']);
        DB::table('service_group_prices')->insert([
            'service_id' => 10,
            'service_type' => 'smm',
            'group_id' => 3,
            'price' => 12.5000,
            'discount' => 10,
            'discount_type' => 2,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('services:group-price-audit', [], $output);
        $text = $output->fetch();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('missing_service', $text);
        $this->assertStringContainsString('effective_price_negative', $text);
    }

    public function test_audit_uses_the_current_service_price_for_automatic_discount_rows(): void
    {
        DB::table('smm_services')->insert(['id' => 10, 'cost' => 35, 'profit' => 5]);
        DB::table('groups')->insert(['id' => 3, 'name' => 'VIP']);
        DB::table('service_group_prices')->insert(['service_id' => 10, 'service_type' => 'smm',
            'group_id' => 3, 'price' => 1, 'auto_price' => true, 'discount' => 30, 'discount_type' => 1]);
        $this->assertSame(0, Artisan::call('services:group-price-audit'));
        DB::table('smm_services')->where('id', 10)->update(['cost' => 10]);
        $this->assertSame(1, Artisan::call('services:group-price-audit'));
    }

    public function test_group_price_audit_reports_broken_references_and_invalid_discount(): void
    {
        DB::table('groups')->insert(['id' => 3, 'name' => 'VIP']);
        DB::table('service_group_prices')->insert([
            'service_id' => 999,
            'service_type' => 'smm',
            'group_id' => 3,
            'price' => 5,
            'discount' => 150,
            'discount_type' => 2,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('services:group-price-audit', [], $output);
        $text = $output->fetch();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('missing_service', $text);
        $this->assertStringContainsString('percent_discount_over_100', $text);
        $this->assertStringContainsString('effective_price_negative', $text);
        $this->assertStringContainsString('999', $text);
    }
}
