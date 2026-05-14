<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_admin_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_completed_management_module_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.apis.index'));
        $this->assertTrue(Route::has('admin.orders.imei.index'));
        $this->assertTrue(Route::has('admin.orders.server.index'));
        $this->assertTrue(Route::has('admin.orders.file.index'));
        $this->assertTrue(Route::has('admin.orders.smm.index'));
        $this->assertTrue(Route::has('admin.services.imei.index'));
        $this->assertTrue(Route::has('admin.services.server.index'));
        $this->assertTrue(Route::has('admin.services.file.index'));
        $this->assertTrue(Route::has('admin.services.smm.index'));
        $this->assertTrue(Route::has('admin.services.server.syncFields'));

        $this->assertSame(
            '/admin/service-management/server-services/123/sync-fields',
            route('admin.services.server.syncFields', ['id' => 123], false)
        );
    }

    public function test_service_create_modals_render_successfully(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->get(route("admin.services.{$kind}.modal.create"))
                ->assertOk()
                ->assertSee('serviceCreateForm', false);
        }
    }

    public function test_local_sources_and_replies_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.sources.index'));
        $this->assertTrue(Route::has('admin.sources.store'));
        $this->assertTrue(Route::has('admin.sources.modal.create'));
        $this->assertTrue(Route::has('admin.replies.index'));
        $this->assertTrue(Route::has('admin.replies.store'));
        $this->assertTrue(Route::has('admin.replies.modal.create'));
    }
}
