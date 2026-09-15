<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SecurityTestCase;

class ServiceWriteValidationTest extends SecurityTestCase
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
        Schema::create('service_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
        });
        foreach (['imei', 'server', 'file', 'smm'] as $index => $kind) {
            DB::table('service_groups')->insert(['id' => $index + 1, 'type' => $kind . '_service']);
        }
        Schema::create('api_providers', function (Blueprint $table): void {
            $table->id();
            $table->boolean('active')->default(true);
        });
        DB::table('api_providers')->insert([['id' => 1], ['id' => 2]]);
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            Schema::create('remote_' . $kind . '_services', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('api_provider_id');
                $table->string('remote_id');
            });
            DB::table('remote_' . $kind . '_services')->insert([
                ['api_provider_id' => 1, 'remote_id' => '101'],
                ['api_provider_id' => 2, 'remote_id' => '202'],
            ]);
        }
        $this->actingAs($this->user('Administrator'));
    }

    private function payload(array $values = []): array
    {
        return array_replace(['name' => 'Service', 'type' => 'service', 'main_field_type' => 'text',
            'source' => 1, 'cost' => 10, 'profit' => 2, 'profit_type' => 1], $values);
    }

    public function test_invalid_prices_flags_and_limits_cannot_partially_create_or_update_any_kind(): void
    {
        $invalid = [
            ['cost', -1], ['cost', '1e30'], ['profit', -1], ['profit_type', 3],
            ['min', -1], ['maximum', -1], ['allow_report_time', -1],
            ['allow_cancel_time', -1], ['reply_expiration', -1], ['ordering', -1],
            ['ordering', 2147483648], ['source', 3], ['source', 'api'],
            ['supplier_id', []], ['remote_id', ['bad']],
        ];
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $id = $this->postJson(route("admin.services.{$kind}.store"), $this->payload())
                ->assertSuccessful()->json('id');
            $before = DB::table($kind . '_services')->where('id', $id)->first();
            foreach ($invalid as [$key, $value]) {
                $payload = $this->payload([$key => $value]);
                $this->postJson(route("admin.services.{$kind}.store"), $payload)
                    ->assertUnprocessable()->assertJsonValidationErrors($key);
                $this->putJson(route("admin.services.{$kind}.update", $id), $payload)
                    ->assertUnprocessable()->assertJsonValidationErrors($key);
                $this->assertEquals($before, DB::table($kind . '_services')->where('id', $id)->first());
                $this->assertDatabaseCount($kind . '_services', 1);
                $this->assertDatabaseCount('service_group_prices', 0);
            }
        }
        Http::assertNothingSent();
    }

    public function test_both_limit_aliases_enforce_ordering_and_zero_maximum_stays_unlimited(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            foreach ([['min', 'max'], ['minimum', 'maximum'], ['minimum', 'max']] as [$min, $max]) {
                $this->postJson(route("admin.services.{$kind}.store"), $this->payload([$min => 8, $max => 3]))
                    ->assertUnprocessable()->assertJsonValidationErrors($max);
                $id = $this->postJson(route("admin.services.{$kind}.store"), $this->payload([$min => 8, $max => 0]))
                    ->assertSuccessful()->json('id');
                $this->putJson(route("admin.services.{$kind}.update", $id), $this->payload([$min => 8, $max => 3]))
                    ->assertUnprocessable()->assertJsonValidationErrors($max);
                $main = json_decode(DB::table($kind . '_services')->where('id', $id)->value('main_field'), true);
                $this->assertSame(['allowed' => 'any', 'minimum' => 8, 'maximum' => 0], $main['rules']);
            }
        }
    }

    public function test_new_api_links_require_the_selected_provider_and_its_own_catalog_service(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $id = $this->postJson(route("admin.services.{$kind}.store"), $this->payload())->json('id');
            $before = DB::table($kind . '_services')->where('id', $id)->first();
            foreach ([
                [['supplier_id' => 999, 'remote_id' => 101], 'supplier_id'],
                [['supplier_id' => 1], 'remote_id'],
                [['supplier_id' => 1, 'remote_id' => 999], 'remote_id'],
                [['supplier_id' => 1, 'remote_id' => 202], 'remote_id'],
                [['supplier_id' => 1, 'api_provider_id' => 2, 'remote_id' => 101], 'api_provider_id'],
                [['supplier_id' => 1, 'remote_id' => 101, 'api_service_remote_id' => 202], 'api_service_remote_id'],
            ] as [$values, $error]) {
                $payload = $this->payload(['source' => 2] + $values);
                $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
                $this->putJson(route("admin.services.{$kind}.update", $id), $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
                $this->assertEquals($before, DB::table($kind . '_services')->where('id', $id)->first());
                $this->assertDatabaseCount($kind . '_services', 1);
            }
        }
        Http::assertNothingSent();
    }

    public function test_api_aliases_persist_and_manual_switch_clears_hidden_links_without_losing_group_prices(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $index => $kind) {
            $remote = $kind === 'smm' ? 'opaque-A' : '101';
            DB::table('remote_' . $kind . '_services')->where('api_provider_id', 1)->update(['remote_id' => $remote]);
            $payload = $this->payload(['source' => 2, 'api_provider_id' => 1, 'api_service_remote_id' => $remote,
                'group_id' => $index + 1, 'group_prices' => [1 => ['price' => 7.1234, 'auto_price' => 0, 'discount' => 1, 'discount_type' => 1]]]);
            $id = $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertSuccessful()->json('id');
            $this->assertDatabaseHas($kind . '_services', ['id' => $id, 'supplier_id' => 1, 'remote_id' => $remote]);
            $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertUnprocessable()->assertJsonValidationErrors('remote_id');
            // A refreshed catalog may no longer contain the original service; editing local data must still work.
            DB::table('remote_' . $kind . '_services')->delete();
            $edit = $this->payload(['source' => 2, 'name' => 'Edited']);
            $this->putJson(route("admin.services.{$kind}.update", $id), $edit)->assertOk();
            $this->assertDatabaseHas($kind . '_services', ['id' => $id, 'supplier_id' => 1, 'remote_id' => $remote, 'group_id' => $index + 1]);
            $this->putJson(route("admin.services.{$kind}.update", $id), $this->payload(['source' => 1,
                'supplier_id' => 1, 'remote_id' => $remote]))->assertOk();
            $this->assertDatabaseHas($kind . '_services', ['id' => $id, 'source' => 1, 'supplier_id' => null, 'remote_id' => null]);
            $this->assertDatabaseHas('service_group_prices', ['service_type' => $kind, 'service_id' => $id,
                'price' => 7.1234, 'auto_price' => 0, 'discount' => 1]);
        }
    }

    public function test_group_retyped_after_request_validation_is_rejected_at_write_time(): void
    {
        $armed = true;
        DB::listen(function ($query) use (&$armed): void {
            if ($armed && str_contains($query->sql, 'service_groups') && str_contains($query->sql, 'count(*)')) {
                $armed = false;
                DB::table('service_groups')->where('id', 1)->update(['type' => 'smm_service']);
            }
        });
        $this->postJson(route('admin.services.imei.store'), $this->payload(['group_id' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors('group_id');
        $this->assertFalse($armed);
        $this->assertDatabaseCount('imei_services', 0);
    }

    public function test_manual_and_automatic_group_prices_cannot_overflow_the_database_column(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->postJson(route("admin.services.{$kind}.store"), $this->payload([
                'group_prices' => [1 => ['price' => 100000000, 'auto_price' => 0]],
            ]))->assertUnprocessable()->assertJsonValidationErrors('group_prices.1.price');
            $this->postJson(route("admin.services.{$kind}.store"), $this->payload([
                'cost' => 99999999, 'profit' => 100, 'profit_type' => 2,
                'group_prices' => [1 => ['price' => 0, 'auto_price' => 1]],
            ]))->assertUnprocessable()->assertJsonValidationErrors('group_prices.1.price');
            $this->assertDatabaseCount($kind . '_services', 0);
        }
    }
}
