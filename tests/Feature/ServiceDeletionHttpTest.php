<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SecurityTestCase;

class ServiceDeletionHttpTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            Schema::create($kind . '_services', function (Blueprint $table): void {
                $table->id();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
            Schema::create($kind . '_orders', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('service_id');
            });
        }
        foreach (['service_group_prices', 'custom_fields'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('service_id');
                $table->string('service_type');
            });
        }
        $this->actingAs($this->user('Administrator'));
    }

    public function test_single_delete_conflict_preserves_service_order_prices_and_fields(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->seedService($kind, 1, true);
            $this->deleteJson(route("admin.services.{$kind}.destroy", [1]))
                ->assertStatus(409)->assertJson(['linked_orders' => 1, 'service_type' => $kind]);
            $this->assertServiceRetained($kind, 1);
            $this->assertDatabaseHas($kind . '_orders', ['service_id' => 1]);
        }
        Http::assertNothingSent();
    }

    public function test_mixed_bulk_delete_rolls_back_the_entire_batch(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            // The unused row sorts first: its deletion must be rolled back too.
            $this->seedService($kind, 1, false);
            $this->seedService($kind, 2, true);
            $this->postJson(route("admin.services.{$kind}.bulk"), ['action' => 'delete', 'ids' => [1, 2]])
                ->assertStatus(409)->assertJson(['service_id' => 2]);
            $this->assertServiceRetained($kind, 1);
            $this->assertServiceRetained($kind, 2);
            $this->assertDatabaseHas($kind . '_orders', ['service_id' => 2]);
        }
        Http::assertNothingSent();
    }

    public function test_unused_services_can_be_deleted_without_removing_other_service_metadata(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->seedService($kind, 1, false);
            $this->seedService($kind, 2, false);
            $this->seedService($kind, 3, true);
            $this->deleteJson(route("admin.services.{$kind}.destroy", [1]))->assertOk();
            $this->postJson(route("admin.services.{$kind}.bulk"), ['action' => 'delete', 'ids' => [2]])
                ->assertOk()->assertJson(['affected' => 1]);
            foreach ([1, 2] as $id) {
                $this->assertDatabaseMissing($kind . '_services', ['id' => $id]);
                $this->assertDatabaseMissing('service_group_prices', ['service_id' => $id, 'service_type' => $kind]);
                $this->assertDatabaseMissing('custom_fields', ['service_id' => $id, 'service_type' => $kind . '_service']);
            }
            $this->assertServiceRetained($kind, 3);
        }
        Http::assertNothingSent();
    }

    public function test_edit_permission_cannot_delete_services_individually_or_in_bulk(): void
    {
        $user = $this->user();
        $user->givePermissionTo(['admin.access', 'services.edit']);
        $this->actingAs($user);
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->seedService($kind, 1, false);
            $this->deleteJson(route("admin.services.{$kind}.destroy", [1]))->assertForbidden();
            $this->postJson(route("admin.services.{$kind}.bulk"), ['action' => 'delete', 'ids' => [1]])->assertForbidden();
            $this->assertServiceRetained($kind, 1);
        }
    }

    private function seedService(string $kind, int $id, bool $withOrder): void
    {
        DB::table($kind . '_services')->insert(['id' => $id, 'active' => true]);
        DB::table('service_group_prices')->insert(['service_id' => $id, 'service_type' => $kind]);
        DB::table('custom_fields')->insert(['service_id' => $id, 'service_type' => $kind . '_service']);
        if ($withOrder) {
            DB::table($kind . '_orders')->insert(['service_id' => $id]);
        }
    }

    private function assertServiceRetained(string $kind, int $id): void
    {
        $this->assertDatabaseHas($kind . '_services', ['id' => $id, 'active' => 1]);
        $this->assertDatabaseHas('service_group_prices', ['service_id' => $id, 'service_type' => $kind]);
        $this->assertDatabaseHas('custom_fields', ['service_id' => $id, 'service_type' => $kind . '_service']);
    }
}
