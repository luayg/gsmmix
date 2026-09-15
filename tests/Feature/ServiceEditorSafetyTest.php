<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ServiceEditorTestCase;

class ServiceEditorSafetyTest extends ServiceEditorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('service_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->integer('ordering')->default(1);
            $table->timestamps();
        });
        Schema::create('api_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        DB::table('api_providers')->insert([['id' => 1, 'name' => 'One'], ['id' => 2, 'name' => 'Two']]);
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            Schema::create('remote_' . $kind . '_services', function (Blueprint $table): void {
                $table->id();
                $table->integer('api_provider_id');
                $table->string('remote_id');
                $table->text('additional_fields')->nullable();
            });
            DB::table('remote_' . $kind . '_services')->insert(['api_provider_id' => 1, 'remote_id' => '42']);
        }
    }

    public function test_invalid_service_values_do_not_write_any_service_kind(): void
    {
        $invalid = [
            ['cost' => -1], ['profit' => -1], ['profit_type' => 3], ['source' => 3],
            ['minimum' => -1], ['maximum' => -1], ['minimum' => 10, 'maximum' => 9],
            ['min' => 10, 'max' => 9], ['allow_report_time' => -1], ['allow_cancel_time' => -1],
            ['reply_expiration' => -1], ['cost' => '1e309'], ['cost' => 999999999],
            ['cost' => 99999999, 'profit' => 100, 'profit_type' => 2],
            ['supplier_id' => []], ['remote_id' => []],
            ['source' => 2], ['source' => 2, 'supplier_id' => 999, 'remote_id' => 42],
            ['source' => 2, 'supplier_id' => 2, 'remote_id' => 42],
            ['source' => 2, 'supplier_id' => 1, 'remote_id' => 999],
        ];
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            DB::table($kind . '_services')->insert(['id' => 1, 'name' => 'Keep']);
            $before = DB::table($kind . '_services')->first();
            foreach ($invalid as $extra) {
                $payload = array_replace($this->payload(), $extra);
                $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertUnprocessable();
                $this->putJson(route("admin.services.{$kind}.update", [1]), $payload)->assertUnprocessable();
                $this->assertDatabaseCount($kind . '_services', 1);
                $this->assertEquals($before, DB::table($kind . '_services')->first());
            }
        }
        Http::assertNothingSent();
    }

    public function test_create_and_update_validate_custom_fields_identically(): void
    {
        $invalid = ['{broken', '{}', 'null', '["invalid"]', '[{}]', '[{"name":"X","options":[{}]}]',
            '[{"name":"X","min":-1}]', '[{"name":"X","min":5,"max":2}]',
            '[{"name":"X","required":"false"}]', '[{"name":"X","maximum":[]}]'];
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            DB::table($kind . '_services')->insert(['id' => 1, 'name' => 'Keep']);
            foreach (['custom_fields', 'custom_fields_json'] as $key) {
                foreach ($invalid as $value) {
                    $payload = $this->payload() + [$key => $value];
                    $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertUnprocessable()->assertJsonValidationErrors($key);
                    $this->putJson(route("admin.services.{$kind}.update", [1]), $payload)->assertUnprocessable()->assertJsonValidationErrors($key);
                    $this->assertDatabaseCount($kind . '_services', 1);
                    $this->assertDatabaseHas($kind . '_services', ['id' => 1, 'name' => 'Keep']);
                    $this->assertDatabaseCount('custom_fields', 0);
                }
            }
        }
    }

    public function test_valid_provider_link_and_manual_transition_preserve_prices_and_clear_routing(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $payload = array_replace($this->payload(), ['source' => 2, 'api_provider_id' => 1,
                'api_service_remote_id' => 42, 'cost' => 0, 'profit' => 0, 'minimum' => 10, 'maximum' => 0]);
            $id = $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertOk()->json('id');
            $this->assertDatabaseHas($kind . '_services', ['id' => $id, 'source' => 2, 'supplier_id' => 1, 'remote_id' => 42]);
            $payload['source'] = 1;
            $this->putJson(route("admin.services.{$kind}.update", [$id]), $payload)->assertOk();
            $this->assertDatabaseHas($kind . '_services', ['id' => $id, 'source' => 1, 'supplier_id' => null, 'remote_id' => null, 'cost' => 0]);
        }
    }

    public function test_group_cannot_change_kind_while_linked_and_ordering_is_saved(): void
    {
        $this->post(route('admin.services.groups.store'), ['name' => 'Empty', 'type' => 'imei', 'ordering' => 7])->assertRedirect();
        $this->assertDatabaseHas('service_groups', ['name' => 'Empty', 'ordering' => 7]);
        $this->putJson(route('admin.services.groups.update', [1]), ['name' => 'Empty', 'type' => 'file'])->assertRedirect();
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $id = DB::table('service_groups')->insertGetId(['name' => 'Keep', 'type' => $kind . '_service', 'ordering' => 3]);
            DB::table($kind . '_services')->insert(['group_id' => $id]);
            $this->putJson(route('admin.services.groups.update', [$id]), ['name' => 'Keep', 'type' => $kind])->assertRedirect();
            $other = $kind === 'imei' ? 'server' : 'imei';
            $this->putJson(route('admin.services.groups.update', [$id]), ['name' => 'Wrong', 'type' => $other])
                ->assertUnprocessable()->assertJsonValidationErrors('type');
            $this->assertDatabaseHas('service_groups', ['id' => $id, 'name' => 'Keep', 'type' => $kind . '_service', 'ordering' => 3]);
        }
    }

    public function test_toggle_and_legacy_edit_routes_resolve_and_handle_missing_records(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            DB::table($kind . '_services')->insert(['id' => 1, 'active' => 0]);
            $url = route("admin.services.{$kind}.toggle", [1]);
            $this->postJson($url)->assertOk()->assertJsonPath('active', true);
            $this->postJson($url, ['active' => false])->assertOk()->assertJsonPath('active', false);
            $this->postJson($url, ['active' => false])->assertOk()->assertJsonPath('active', false);
            $this->postJson($url, ['active' => 'invalid'])->assertUnprocessable();
            $this->get(route("admin.services.{$kind}.modal.edit", [1]))
                ->assertRedirect(route("admin.services.{$kind}.index", ['edit_service' => 1]));
            $this->get(route("admin.services.{$kind}.modal.edit", [999]))->assertNotFound();
            $this->postJson(route("admin.services.{$kind}.toggle", [999]))->assertNotFound();
        }
    }

    public function test_service_route_actions_exist(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with((string) $route->getName(), 'admin.services.')) continue;
            $action = $route->getActionName();
            if (!str_contains($action, '@')) continue;
            [$controller, $method] = explode('@', $action);
            $this->assertTrue(method_exists($controller, $method), $action);
        }
    }

    public function test_field_sync_uses_only_the_linked_provider_and_preserves_other_values(): void
    {
        DB::table('server_services')->insert(['id' => 1, 'name' => 'Keep', 'source' => 2, 'supplier_id' => 1,
            'remote_id' => 42, 'cost' => 7.25, 'profit' => 2, 'params' => json_encode(['marker' => 'keep'])]);
        DB::table('remote_server_services')->where('api_provider_id', 1)->update([
            'additional_fields' => json_encode([['fieldname' => 'Email', 'input' => 'email', 'fieldtype' => 'email', 'required' => 'yes']]),
        ]);
        DB::table('remote_server_services')->insert(['api_provider_id' => 2, 'remote_id' => 42,
            'additional_fields' => json_encode([['fieldname' => 'Wrong provider']])]);
        $url = route('admin.services.server.syncFields', [1]);
        $this->postJson($url)->assertOk()->assertJsonPath('count', 1);
        $this->assertDatabaseHas('custom_fields', ['service_type' => 'server_service', 'service_id' => 1, 'input' => 'email', 'required' => 1]);
        $row = DB::table('server_services')->first();
        $this->assertSame('keep', json_decode($row->params, true)['marker']);
        $this->assertSame('Email', json_decode($row->params, true)['custom_fields'][0]['name']);
        $this->assertSame('Keep', $row->name);
        $this->assertEquals(7.25, $row->cost);
        $before = DB::table('custom_fields')->get();
        DB::table('remote_server_services')->where('api_provider_id', 1)->update(['additional_fields' => '[]']);
        $this->postJson($url)->assertUnprocessable();
        $this->assertEquals($before, DB::table('custom_fields')->get());
        DB::table('remote_server_services')->where('api_provider_id', 1)->delete();
        $this->postJson($url)->assertUnprocessable();
        $this->assertEquals($before, DB::table('custom_fields')->get());
        DB::table('server_services')->update(['source' => 1]);
        $this->postJson($url)->assertUnprocessable();
        $this->postJson(route('admin.services.server.syncFields', [999]))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_read_only_and_guest_users_cannot_use_service_edit_actions(): void
    {
        $reader = $this->user();
        $reader->givePermissionTo(['admin.access', 'services.view']);
        $this->actingAs($reader);
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->postJson(route("admin.services.{$kind}.toggle", [1]))->assertForbidden();
            $this->getJson(route("admin.services.{$kind}.modal.edit", [1]))->assertForbidden();
        }
        $this->postJson(route('admin.services.server.syncFields', [1]))->assertForbidden();
        auth()->logout();
        $this->postJson(route('admin.services.server.syncFields', [1]))->assertUnauthorized();
        $this->postJson(route('admin.services.imei.toggle', [1]))->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_duplicate_provider_links_are_validation_errors_and_existing_link_can_be_saved(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $payload = array_replace($this->payload(), ['source' => 2, 'supplier_id' => 1, 'remote_id' => 42]);
            $id = $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertOk()->json('id');
            $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertUnprocessable()->assertJsonValidationErrors('remote_id');
            $this->putJson(route("admin.services.{$kind}.update", [$id]), $payload)->assertOk();
            $this->assertDatabaseCount($kind . '_services', 1);
        }
    }

    public function test_select_options_do_not_duplicate_after_repeated_edit_round_trips(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $payload = $this->payload() + ['custom_fields' => [
                ['name' => 'Choice', 'input' => 'choice', 'type' => 'select', 'options' => 'First,Second', 'active' => 1],
            ]];
            $id = $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertOk()->json('id');
            for ($i = 0; $i < 3; $i++) {
                $fields = $this->getJson(route("admin.services.{$kind}.show.json", [$id]))->assertOk()->json('service.custom_fields');
                $this->assertSame('First,Second', $fields[0]['options']);
                $payload['custom_fields'] = $fields;
                $this->putJson(route("admin.services.{$kind}.update", [$id]), $payload)->assertOk();
            }
        }
    }

    private function payload(): array
    {
        return ['name' => 'Service', 'type' => 'service', 'source' => 1, 'main_field_type' => 'text'];
    }
}
