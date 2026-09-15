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
        DB::table('groups')->insert([['id' => 1], ['id' => 2], ['id' => 3]]);
        Schema::create('service_group_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('group_id');
            $table->string('service_type');
            $table->decimal('price', 12, 4);
            $table->boolean('auto_price')->default(false);
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
            [1 => ['price' => 10, 'auto_price' => 'invalid']],
            [1 => ['price' => 1000, 'auto_price' => 1, 'discount' => 1, 'discount_type' => 1]],
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

    public function test_manual_prices_reset_and_automatic_discounts_survive_the_http_round_trip_for_all_editors(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $payload = $this->payload() + ['cost' => 20, 'profit' => 5, 'profit_type' => 1,
                'group_prices' => [
                    1 => ['price' => 25, 'auto_price' => 0, 'discount' => 2, 'discount_type' => 1],
                    2 => ['price' => 12.3456, 'discount' => 10, 'discount_type' => 2],
                    3 => ['price' => 0, 'auto_price' => 0, 'discount' => 0, 'discount_type' => 1],
                ]];
            $created = $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertSuccessful();
            $id = (int) $created->json('id');
            $jsonUrl = route("admin.services.{$kind}.show.json", [$id]);
            $updateUrl = route("admin.services.{$kind}.update", [$id]);
            $readPrices = fn () => collect($this->getJson($jsonUrl)->assertOk()->json('service.group_prices'))->keyBy('group_id');

            $payload['cost'] = 35;
            $payload['group_prices'] = $readPrices()->toArray();
            $this->putJson($updateUrl, $payload)->assertOk();
            $prices = $readPrices();
            $this->assertSame(25.0, (float) $prices[1]['price']);
            $this->assertFalse($prices[1]['auto_price']);
            $this->assertSame(12.3456, (float) $prices[2]['price']);
            $this->assertFalse($prices[2]['auto_price']);
            $this->assertSame(0.0, (float) $prices[3]['price']);

            // Reset clears the discount and persists automatic mode, not just its preview.
            $payload['group_prices'][1] = ['price' => 40, 'auto_price' => '1', 'discount' => 0, 'discount_type' => 1];
            $this->putJson($updateUrl, $payload)->assertOk();
            $this->assertTrue($readPrices()[1]['auto_price']);
            $payload['group_prices'] = $readPrices()->toArray();
            $payload['cost'] = 45;
            $this->putJson($updateUrl, $payload)->assertOk();
            $this->assertSame(50.0, (float) $readPrices()[1]['price']);
            $this->assertSame(0.0, (float) $readPrices()[1]['discount']);

            // Recompute automatic values on the server, including fixed and percent discounts.
            foreach ([[1, 3, 57.0], [2, 10, 54.0]] as [$type, $discount, $expectedFinal]) {
                $payload['group_prices'][1] = ['price' => 999, 'auto_price' => 1,
                    'discount' => $discount, 'discount_type' => $type];
                $this->putJson($updateUrl, $payload)->assertOk();
                $this->assertDatabaseHas('service_group_prices', ['service_type' => $kind,
                    'service_id' => $id, 'group_id' => 1, 'price' => 50, 'auto_price' => 1]);
                // A provider cost change must also affect automatic prices without resaving rows.
                DB::table($kind . '_services')->where('id', $id)->update(['cost' => 55]);
                $prices = $readPrices();
                $this->assertSame(60.0, (float) $prices[1]['price']);
                $this->assertSame($discount, (int) $prices[1]['discount']);
                $this->assertSame($type, (int) $prices[1]['discount_type']);
                $model = \App\Models\ServiceGroupPrice::where('service_type', $kind)
                    ->where('service_id', $id)->where('group_id', 1)->firstOrFail();
                $this->assertSame($expectedFinal, $model->finalPrice(['cost' => 55, 'profit' => 5, 'profit_type' => 1]));
                $this->assertSame(12.3456, (float) $prices[2]['price']);
                $this->assertSame(0.0, (float) $prices[3]['price']);
            }

            unset($payload['group_prices']);
            $payload['cost'] = 100;
            $payload['profit'] = 10;
            $payload['profit_type'] = 2;
            $this->putJson($updateUrl, $payload)->assertOk();
            $this->assertTrue($readPrices()[1]['auto_price']);
            $this->assertSame(110.0, (float) $readPrices()[1]['price']);
        }
        Http::assertNothingSent();
    }

    private function payload(): array
    {
        return ['name' => 'Test service', 'type' => 'service', 'main_field_type' => 'text', 'source' => 1];
    }
}
