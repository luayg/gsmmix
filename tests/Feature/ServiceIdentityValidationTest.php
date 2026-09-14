<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SecurityTestCase;

class ServiceIdentityValidationTest extends SecurityTestCase
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
        Schema::create('service_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
        });
        foreach (['imei', 'server', 'file', 'smm'] as $index => $kind) {
            DB::table('service_groups')->insert(['id' => $index + 1, 'type' => $kind . '_service']);
        }
        $this->actingAs($this->user('Administrator'));
    }

    public function test_update_rejects_duplicate_alias_without_modifying_the_service(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            DB::table($kind . '_services')->insert([
                ['id' => 1, 'alias' => 'original', 'name' => 'Keep'],
                ['id' => 2, 'alias' => 'occupied', 'name' => 'Other'],
            ]);
            $this->putJson(route("admin.services.{$kind}.update", [1]), $this->payload() + ['alias' => 'occupied'])
                ->assertUnprocessable()->assertJsonValidationErrors('alias');
            $this->assertDatabaseHas($kind . '_services', ['id' => 1, 'alias' => 'original', 'name' => 'Keep']);
            $this->assertDatabaseHas($kind . '_services', ['id' => 2, 'alias' => 'occupied']);
        }
        Http::assertNothingSent();
    }

    public function test_unchanged_and_cross_kind_aliases_remain_valid_and_create_keeps_suffix_behavior(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            DB::table($kind . '_services')->insert(['id' => 1, 'alias' => 'shared']);
            $this->putJson(route("admin.services.{$kind}.update", [1]), $this->payload() + ['alias' => 'shared'])->assertOk();
            $this->putJson(route("admin.services.{$kind}.update", [1]), $this->payload())->assertOk();
            $this->assertDatabaseHas($kind . '_services', ['id' => 1, 'alias' => 'shared']);
            $this->postJson(route("admin.services.{$kind}.store"), $this->payload() + ['alias' => 'shared'])->assertSuccessful();
            $this->assertDatabaseHas($kind . '_services', ['alias' => 'shared-1']);
        }
    }

    public function test_wrong_kind_or_missing_group_is_rejected_before_create_and_update(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $index => $kind) {
            DB::table($kind . '_services')->insert(['id' => 1, 'alias' => 'keep', 'name' => 'Keep', 'group_id' => $index + 1]);
            foreach ([999, (($index + 1) % 4) + 1] as $invalidGroup) {
                $payload = $this->payload() + ['group_id' => $invalidGroup];
                $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertUnprocessable()->assertJsonValidationErrors('group_id');
                $this->putJson(route("admin.services.{$kind}.update", [1]), $payload)->assertUnprocessable()->assertJsonValidationErrors('group_id');
                $this->assertDatabaseCount($kind . '_services', 1);
                $this->assertDatabaseHas($kind . '_services', ['id' => 1, 'name' => 'Keep', 'group_id' => $index + 1]);
            }
        }
        Http::assertNothingSent();
    }

    public function test_matching_group_is_accepted_for_create_update_and_explicit_clear(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $index => $kind) {
            $payload = $this->payload() + ['group_id' => $index + 1];
            $response = $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertSuccessful();
            $id = (int)$response->json('id');
            $this->assertDatabaseHas($kind . '_services', ['id' => $id, 'group_id' => $index + 1]);
            $this->putJson(route("admin.services.{$kind}.update", [$id]), $payload)->assertOk();
            $this->assertDatabaseHas($kind . '_services', ['id' => $id, 'group_id' => $index + 1]);
            $this->putJson(route("admin.services.{$kind}.update", [$id]), $this->payload() + ['group_id' => null])->assertOk();
            $this->assertDatabaseHas($kind . '_services', ['id' => $id, 'group_id' => null]);
        }
    }

    private function payload(): array
    {
        return ['name' => 'Edited', 'type' => 'service', 'main_field_type' => 'text', 'source' => 1];
    }
}
