<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SecurityTestCase;

class ServiceGroupPriceValidationTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
        });
        DB::table('groups')->insert(['id' => 1]);
        Schema::create('service_group_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('group_id');
            $table->string('service_type');
            $table->decimal('price', 12, 4);
            $table->decimal('discount', 12, 4);
            $table->integer('discount_type');
            $table->timestamps();
        });
        Schema::create('custom_fields', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->string('service_type');
        });
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            Schema::create($kind . '_services', function (Blueprint $table): void {
                $table->id();
                foreach (['alias', 'type', 'name', 'time', 'info', 'main_field', 'params', 'remote_id'] as $name) {
                    $table->text($name)->nullable();
                }
                foreach (['group_id', 'source', 'supplier_id', 'profit_type', 'active', 'allow_bulk',
                    'allow_duplicates', 'reply_with_latest', 'allow_report', 'allow_report_time',
                    'allow_cancel', 'allow_cancel_time', 'use_remote_cost', 'use_remote_price',
                    'stop_on_api_change', 'needs_approval', 'reply_expiration',
                    'reject_on_missing_reply', 'ordering'] as $name) {
                    $table->integer($name)->nullable();
                }
                $table->decimal('cost', 12, 4)->default(0);
                $table->decimal('profit', 12, 4)->default(0);
                $table->timestamps();
            });
        }
        $this->actingAs($this->user('Administrator'));
    }

    public function test_invalid_group_prices_are_rejected_before_create_or_update_writes(): void
    {
        $cases = [
            [999 => ['price' => 10]],
            ['invalid' => ['price' => 10]],
            [1 => ['price' => 10, 'discount' => 101, 'discount_type' => 2]],
            [1 => ['price' => 10, 'discount' => 11, 'discount_type' => 1]],
            [1 => ['price' => -1]],
            [1 => ['price' => 10, 'discount' => -1]],
            [1 => ['price' => 10, 'discount_type' => 3]],
            [1 => 'not a price row'],
        ];
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            DB::table($kind . '_services')->insert(['id' => 1, 'name' => 'Unchanged']);
            DB::table('service_group_prices')->insert([
                'service_type' => $kind, 'service_id' => 1, 'group_id' => 1,
                'price' => 20, 'discount' => 2, 'discount_type' => 1,
            ]);
            DB::table('custom_fields')->insert(['service_type' => $kind . '_service', 'service_id' => 1]);
            foreach ($cases as $prices) {
                $payload = $this->payload() + ['group_prices' => $prices];
                $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertUnprocessable();
                $this->putJson(route("admin.services.{$kind}.update", [1]), $payload)->assertUnprocessable();
                $this->assertDatabaseCount($kind . '_services', 1);
                $this->assertDatabaseHas($kind . '_services', ['id' => 1, 'name' => 'Unchanged']);
                $this->assertDatabaseHas('service_group_prices', [
                    'service_type' => $kind, 'service_id' => 1, 'group_id' => 1,
                    'price' => 20, 'discount' => 2, 'discount_type' => 1,
                ]);
                $this->assertDatabaseHas('custom_fields', ['service_type' => $kind . '_service', 'service_id' => 1]);
            }
        }
        Http::assertNothingSent();
    }

    public function test_valid_prices_and_zero_effective_price_boundaries_save_and_omission_preserves_prices(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            foreach ([[12.3456, 10, 2], [10, 100, 2], [10, 10, 1], [0, 0, 1]] as [$price, $discount, $type]) {
                $prices = [1 => ['price' => $price, 'discount' => $discount, 'discount_type' => $type]];
                $response = $this->postJson(route("admin.services.{$kind}.store"), $this->payload() + ['group_prices' => $prices]);
                $response->assertSuccessful();
                $id = (int)$response->json('id');
                $this->assertGreaterThan(0, $id);
                $this->putJson(route("admin.services.{$kind}.update", [$id]), $this->payload() + ['group_prices' => $prices])->assertOk();
                $this->putJson(route("admin.services.{$kind}.update", [$id]), $this->payload())->assertOk();
                $this->assertDatabaseHas('service_group_prices', [
                    'service_type' => $kind, 'service_id' => $id, 'group_id' => 1,
                    'price' => $price, 'discount' => $discount, 'discount_type' => $type,
                ]);
            }
        }
        Http::assertNothingSent();
    }

    private function payload(): array
    {
        return ['name' => 'Test service', 'type' => 'service', 'main_field_type' => 'text', 'source' => 1];
    }
}
