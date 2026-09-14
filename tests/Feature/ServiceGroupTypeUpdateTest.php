<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SecurityTestCase;

class ServiceGroupTypeUpdateTest extends SecurityTestCase
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
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            Schema::create($kind . '_services', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('group_id')->nullable();
            });
        }
        $this->actingAs($this->user('Administrator'));
    }

    public function test_linked_group_cannot_change_kind_or_partially_save_other_fields(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $index => $kind) {
            $id = $index + 1;
            DB::table('service_groups')->insert(['id' => $id, 'name' => 'Keep', 'type' => $kind . '_service', 'ordering' => 7]);
            DB::table($kind . '_services')->insert(['group_id' => $id]);
            $target = $kind === 'smm' ? 'imei' : 'smm';
            $this->putJson(route('admin.services.groups.update', $id), ['name' => 'Changed', 'type' => $target, 'ordering' => 9])
                ->assertUnprocessable()->assertJsonValidationErrors('type');
            $this->assertDatabaseHas('service_groups', ['id' => $id, 'name' => 'Keep', 'type' => $kind . '_service', 'ordering' => 7]);
            $this->assertDatabaseHas($kind . '_services', ['group_id' => $id]);
        }
    }

    public function test_same_kind_alias_can_be_saved_and_unused_group_can_change_kind(): void
    {
        DB::table('service_groups')->insert(['id' => 1, 'name' => 'Old', 'type' => 'smm_service']);
        DB::table('smm_services')->insert(['group_id' => 1]);
        $this->putJson(route('admin.services.groups.update', 1), ['name' => 'Renamed', 'type' => 'smm'])->assertRedirect();
        $this->assertDatabaseHas('service_groups', ['id' => 1, 'name' => 'Renamed', 'type' => 'smm_service']);

        DB::table('service_groups')->insert(['id' => 2, 'name' => 'Unused', 'type' => 'imei_service']);
        $this->putJson(route('admin.services.groups.update', 2), ['name' => 'Moved', 'type' => 'file'])->assertRedirect();
        $this->assertDatabaseHas('service_groups', ['id' => 2, 'name' => 'Moved', 'type' => 'file_service']);
    }

    public function test_smm_group_forms_keep_the_existing_canonical_kind_selected(): void
    {
        DB::table('service_groups')->insert(['id' => 1, 'name' => 'SMM group', 'type' => 'smm_service']);
        $this->get(route('admin.services.groups.edit', 1))->assertOk()->assertSee('value="smm_service" selected', false);
        $this->get(route('admin.services.groups.modal.edit', 1))->assertOk()->assertSee('value="smm_service" selected', false);
    }
}
