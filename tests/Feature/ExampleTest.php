<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_renders_public_homepage(): void
    {
        $response = $this->get('/');
        $response->assertOk()->assertViewIs('site.home');
    }

    public function test_admin_dashboard_requires_authentication(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_admin_service_modals_require_authentication(): void
    {
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->get(route("admin.services.{$kind}.modal.create"))
                ->assertRedirect(route('login'));
        }
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
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $this->assertTrue(Route::has("admin.services.{$kind}.show.json"));
            $this->assertTrue(Route::has("admin.services.{$kind}.update"));
            $this->assertTrue(Route::has("admin.services.{$kind}.bulk"));
        }
    }

    public function test_registered_controller_actions_have_public_implementations(): void
    {
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (!str_contains($action, '@')) {
                continue;
            }
            [$controller, $method] = explode('@', $action, 2);
            $this->assertTrue(method_exists($controller, $method), "Missing action: {$action}");
            $this->assertTrue((new \ReflectionMethod($controller, $method))->isPublic(), "Non-public action: {$action}");
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

    public function test_store_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.store.categories.index'));
        $this->assertTrue(Route::has('admin.store.categories.store'));
        $this->assertTrue(Route::has('admin.store.categories.modal.create'));
        $this->assertTrue(Route::has('admin.store.products.index'));
        $this->assertTrue(Route::has('admin.store.products.store'));
        $this->assertTrue(Route::has('admin.store.products.modal.create'));
    }
}
