<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\Support\SecurityTestCase;

class ServiceCreateModalTest extends SecurityTestCase
{
    public function test_administrator_can_render_each_service_create_modal(): void
    {
        $this->actingAs($this->user('Administrator'));
        $this->assertServiceModalsRender();
    }

    public function test_delegated_service_creator_can_render_each_modal(): void
    {
        $user = $this->user();
        $user->givePermissionTo(['admin.access', 'services.create']);
        $this->actingAs($user);
        $this->assertServiceModalsRender();
    }

    public function test_service_view_permission_does_not_allow_create_modals(): void
    {
        $user = $this->user();
        $user->givePermissionTo(['admin.access', 'services.view']);
        $this->actingAs($user);

        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->get(route("admin.services.{$kind}.modal.create"))->assertForbidden();
        }
        Http::assertNothingSent();
    }

    private function assertServiceModalsRender(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->get(route("admin.services.{$kind}.modal.create"))
                ->assertOk()
                ->assertViewIs("admin.services.{$kind}._modal_create")
                ->assertSee('id="serviceCreateForm"', false)
                ->assertSee('action="' . route("admin.services.{$kind}.store") . '"', false);
        }
        Http::assertNothingSent();
    }
}
