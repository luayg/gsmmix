<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SecurityTestCase;

class ServiceCustomFieldUpdateTest extends SecurityTestCase
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
        Schema::table('custom_fields', function (Blueprint $table): void {
            foreach (['name', 'input', 'field_type', 'field_options', 'description', 'validation'] as $name) {
                $table->text($name)->nullable();
            }
            foreach (['minimum', 'maximum', 'required', 'active', 'ordering'] as $name) {
                $table->integer($name)->nullable();
            }
            $table->timestamps();
        });
        $this->actingAs($this->user('Administrator'));
    }

    public function test_malformed_create_fields_are_rejected_before_any_rows_are_written(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            foreach (['custom_fields', 'custom_fields_json'] as $key) {
                foreach (['{broken', '{}', 'null', '["invalid"]', '[{}]', '[{"name":"X","options":[{}]}]'] as $value) {
                    $this->postJson(route("admin.services.{$kind}.store"), $this->payload() + [$key => $value])
                        ->assertUnprocessable()->assertJsonValidationErrors($key);
                    $this->assertDatabaseCount($kind . '_services', 0);
                    $this->assertDatabaseCount('custom_fields', 0);
                    $this->assertDatabaseCount('service_group_prices', 0);
                }
            }
        }
        Http::assertNothingSent();
    }

    public function test_create_explicit_empty_list_wins_over_alternate_json_fields(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $response = $this->postJson(route("admin.services.{$kind}.store"), $this->payload() + [
                'custom_fields' => [],
                'custom_fields_json' => '[{"name":"Ignored","input":"ignored"}]',
            ])->assertSuccessful();
            $this->assertDatabaseCount('custom_fields', 0);
            $raw = DB::table($kind . '_services')->where('id', $response->json('id'))->value('params');
            $this->assertSame([], json_decode($raw, true)['custom_fields']);
        }
    }

    public function test_omission_preserves_fields_and_params_even_with_empty_params_input(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->seedService($kind);
            $before = DB::table('custom_fields')->where('service_type', $kind . '_service')->first();
            foreach ([[], ['params' => '{}'], ['params' => '{"format":"new","custom_fields":[]}']] as $extra) {
                $this->putJson(route("admin.services.{$kind}.update", [1]), $this->payload() + $extra)->assertOk();
                $this->assertEquals($before, DB::table('custom_fields')->where('service_type', $kind . '_service')->first());
                $params = $this->params($kind);
                $this->assertSame('keep', $params['marker']);
                $this->assertSame('Original', $params['custom_fields'][0]['name']);
            }
            $this->assertSame('new', $this->params($kind)['format']);
        }
        Http::assertNothingSent();
    }

    public function test_explicit_empty_list_clears_fields_in_both_storage_locations(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->seedService($kind);
            // Explicit raw [] takes precedence over a stale alternate input.
            $this->putJson(route("admin.services.{$kind}.update", [1]), $this->payload() + [
                'custom_fields' => [], 'custom_fields_json' => json_encode([$this->field()]),
            ])->assertOk();
            $this->assertDatabaseMissing('custom_fields', ['service_type' => $kind . '_service', 'service_id' => 1]);
            $this->assertSame([], $this->params($kind)['custom_fields']);
            $this->assertSame('keep', $this->params($kind)['marker']);
        }
    }

    public function test_replacement_updates_both_locations_and_preserves_unrelated_params(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->seedService($kind);
            foreach (['custom_fields', 'custom_fields_json'] as $key) {
                $field = $this->field();
                $field['name'] = 'Replacement';
                $field['input_name'] = 'replacement';
                $value = $key === 'custom_fields' ? [$field] : json_encode([$field]);
                $this->putJson(route("admin.services.{$kind}.update", [1]), $this->payload() + [$key => $value])->assertOk();
                $this->assertDatabaseHas('custom_fields', ['service_type' => $kind . '_service', 'service_id' => 1, 'input' => 'replacement']);
                $this->assertSame(1, DB::table('custom_fields')->where('service_type', $kind . '_service')->count());
                $params = $this->params($kind);
                $this->assertSame('Replacement', $params['custom_fields'][0]['name']);
                $this->assertSame('keep', $params['marker']);
                $raw = DB::table($kind . '_services')->value('params');
                $this->assertIsArray(json_decode($raw, true), 'Params must be encoded exactly once.');
            }
        }
        Http::assertNothingSent();
    }

    public function test_malformed_fields_are_rejected_without_modifying_existing_data(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->seedService($kind);
            $before = DB::table($kind . '_services')->first();
            $fieldsBefore = DB::table('custom_fields')->where('service_type', $kind . '_service')->get();
            foreach (['custom_fields', 'custom_fields_json'] as $key) {
                foreach (['{broken', '{}', 'null', '42', '["invalid"]', '[{}]', '[{"name":""}]', '[{"name":"X","options":[{}]}]'] as $value) {
                    $this->putJson(route("admin.services.{$kind}.update", [1]), $this->payload() + [$key => $value])
                        ->assertUnprocessable()->assertJsonValidationErrors($key);
                    $this->assertEquals($before, DB::table($kind . '_services')->first());
                    $this->assertEquals($fieldsBefore, DB::table('custom_fields')->where('service_type', $kind . '_service')->get());
                }
            }
        }
        Http::assertNothingSent();
    }

    public function test_legacy_double_encoded_params_survive_field_replacement(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->seedService($kind);
            $raw = DB::table($kind . '_services')->value('params');
            DB::table($kind . '_services')->update(['params' => json_encode($raw)]);
            $this->putJson(route("admin.services.{$kind}.update", [1]), $this->payload() + ['custom_fields_json' => '[]'])->assertOk();
            $this->assertSame('keep', $this->params($kind)['marker']);
            $this->assertSame([], $this->params($kind)['custom_fields']);
        }
    }

    public function test_edit_json_exposes_saved_field_properties_and_readable_description(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->seedService($kind);
            DB::table('custom_fields')->where('service_type', $kind . '_service')->update([
                'name' => json_encode(['en' => 'Saved label']),
                'input' => 'exact_api_key', 'field_type' => 'text',
                'description' => json_encode(['en' => 'Readable description']),
                'active' => 0, 'required' => 1, 'minimum' => 3, 'maximum' => 48,
                'validation' => 'alphanumeric', 'ordering' => 1,
            ]);
            $this->getJson(route("admin.services.{$kind}.show.json", ['service' => 1]))
                ->assertOk()
                ->assertJsonPath('service.custom_fields.0.name', 'Saved label')
                ->assertJsonPath('service.custom_fields.0.input', 'exact_api_key')
                ->assertJsonPath('service.custom_fields.0.description', 'Readable description')
                ->assertJsonPath('service.custom_fields.0.active', 0)
                ->assertJsonPath('service.custom_fields.0.minimum', 3)
                ->assertJsonPath('service.custom_fields.0.maximum', 48)
                ->assertJsonPath('service.custom_fields.0.validation', 'alphanumeric');
        }
        Http::assertNothingSent();
    }

    public function test_service_json_is_encoded_once_and_round_trips_through_create_edit_and_read(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $payload = [
                'name' => 'خدمة اختبار', 'time' => '1–2 hours', 'info' => '<p>Keep this description</p>',
                'type' => 'service', 'main_field_type' => 'text', 'main_field_label' => 'Device ID',
                'allowed_characters' => 'alphanumeric', 'minimum' => 3, 'maximum' => 48,
                'source' => 1, 'params' => json_encode(['marker' => 'preserved']),
            ];
            $response = $this->postJson(route("admin.services.{$kind}.store"), $payload)->assertSuccessful();
            $id = (int)$response->json('id');
            foreach (['created', 'updated'] as $stage) {
                if ($stage === 'updated') {
                    $payload['name'] = 'Edited خدمة';
                    $this->putJson(route("admin.services.{$kind}.update", [$id]), $payload)->assertOk();
                }
                $row = DB::table($kind . '_services')->where('id', $id)->first();
                foreach (['name', 'time', 'info', 'main_field', 'params'] as $attribute) {
                    $this->assertIsArray(json_decode($row->$attribute, true), "{$kind} {$stage} {$attribute} must be encoded exactly once");
                }
                $this->getJson(route("admin.services.{$kind}.show.json", ['service' => $id]))
                    ->assertOk()
                    ->assertJsonPath('service.name', $payload['name'])
                    ->assertJsonPath('service.time', $payload['time'])
                    ->assertJsonPath('service.info', $payload['info'])
                    ->assertJsonPath('service.main_field.label.en', 'Device ID')
                    ->assertJsonPath('service.main_field.rules.minimum', 3)
                    ->assertJsonPath('service.main_field.rules.maximum', 48)
                    ->assertJsonPath('service.params.marker', 'preserved');
            }
        }
        Http::assertNothingSent();
    }

    public function test_reading_legacy_double_encoded_service_json_does_not_rewrite_storage(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $values = [
                'name' => ['en' => 'Legacy name'], 'time' => ['en' => 'Legacy time'],
                'info' => ['en' => 'Legacy info'],
                'main_field' => ['type' => 'text', 'rules' => ['minimum' => 7]],
                'params' => ['marker' => 'legacy'],
            ];
            foreach ($values as $key => $value) {
                $values[$key] = json_encode(json_encode($value));
            }
            DB::table($kind . '_services')->insert(['id' => 1] + $values);
            $this->getJson(route("admin.services.{$kind}.show.json", ['service' => 1]))
                ->assertOk()->assertJsonPath('service.name', 'Legacy name')
                ->assertJsonPath('service.time', 'Legacy time')
                ->assertJsonPath('service.info', 'Legacy info')
                ->assertJsonPath('service.main_field.rules.minimum', 7)
                ->assertJsonPath('service.params.marker', 'legacy');
            $this->assertDatabaseHas($kind . '_services', ['id' => 1] + $values);
        }
        Http::assertNothingSent();
    }

    private function seedService(string $kind): void
    {
        DB::table($kind . '_services')->insert([
            'id' => 1, 'name' => 'Original', 'params' => json_encode(['marker' => 'keep', 'custom_fields' => [$this->field()]]),
        ]);
        DB::table('custom_fields')->insert([
            'service_type' => $kind . '_service', 'service_id' => 1, 'name' => 'Original', 'input' => 'original',
        ]);
    }

    private function params(string $kind): array
    {
        $value = DB::table($kind . '_services')->value('params');
        for ($i = 0; $i < 2 && is_string($value); $i++) {
            $value = json_decode($value, true);
        }
        return $value;
    }

    private function field(): array
    {
        return ['name' => 'Original', 'input_name' => 'original', 'field_type' => 'text', 'active' => 1];
    }

    private function payload(): array
    {
        return ['name' => 'Edited', 'type' => 'service', 'main_field_type' => 'text', 'source' => 1];
    }
}
