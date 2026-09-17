<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AssignAdminRoleSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\Support\SecurityTestCase;

class AdminAccessTest extends SecurityTestCase
{
    public function test_guests_are_redirected_for_html_and_receive_401_for_json(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('home'));
        $this->getJson('/admin/apis')->assertUnauthorized();
        $this->getJson('/admin/users/999999')->assertUnauthorized();
    }

    public function test_basic_users_cannot_access_admin_even_with_a_module_permission(): void
    {
        $user = $this->user('Basic');
        $user->givePermissionTo('users.view');
        $this->actingAs($user)->getJson('/admin/users')->assertForbidden();
        $this->get('/admin/dashboard')->assertForbidden();
    }

    public function test_administrator_can_access_a_real_admin_route(): void
    {
        $this->actingAs($this->user('Administrator'))
            ->get('/admin/reports/users')->assertOk()->assertSee('User reports');
    }

    public function test_administration_responses_are_not_cacheable(): void
    {
        $response = $this->actingAs($this->user('Administrator'))->get('/admin/reports/users');
        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_support_can_read_but_cannot_write_orders(): void
    {
        Route::middleware('web')->get('/admin/security-read', fn () => response('read'))
            ->name('admin.orders.view');
        Route::middleware('web')->post('/admin/security-write', fn () => response('written'))
            ->name('admin.orders.update');
        $this->actingAs($this->user('Support'))->get('/admin/security-read')->assertOk();
        $this->postJson('/admin/security-write')->assertForbidden();
    }

    public function test_manager_cannot_delete_through_the_bulk_edit_endpoint(): void
    {
        Route::middleware('web')->post('/admin/security-bulk', fn () => response('updated'))
            ->name('admin.services.imei.bulk');
        $this->actingAs($this->user('Manager'))
            ->postJson('/admin/security-bulk', ['action' => 'active'])->assertOk();
        $this->postJson('/admin/security-bulk', ['action' => 'delete'])->assertForbidden();
        $this->postJson('/admin/security-bulk', ['action' => ['delete']])->assertForbidden();
    }

    public function test_permissions_cannot_be_used_to_promote_a_non_administrator(): void
    {
        $manager = $this->user('Manager');
        $manager->givePermissionTo(['roles.view', 'roles.edit', 'permissions.edit', 'users.edit']);
        $this->actingAs($manager)->get('/admin/roles')->assertForbidden();
        $this->postJson('/admin/users/' . $manager->id . '/roles', ['roles' => ['Administrator']])
            ->assertForbidden();
        $this->putJson('/admin/users/' . $manager->id, ['roles' => ['Administrator']])
            ->assertForbidden();
        $this->assertFalse($manager->fresh()->hasRole('Administrator'));
    }

    public function test_inactive_administrator_is_logged_out(): void
    {
        $this->actingAs($this->user('Administrator', 'inactive'))
            ->getJson('/admin/reports/users')->assertUnauthorized();
        $this->assertGuest('web');
    }

    public function test_valid_username_login_survives_a_fresh_session_runtime(): void
    {
        $user = $this->user('Administrator');
        $this->post('/login', [
            'login' => $user->username,
            'password' => 'A-strong-test-password-123!',
            'remember' => 'on',
        ])->assertRedirect(route('admin.dashboard'));
        $this->rememberSessionCookie();
        $this->get('/admin/reports/users')->assertOk();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_email_login_works_and_rotates_the_session_id(): void
    {
        $user = $this->user('Administrator');
        $this->get('/login')->assertOk();
        $before = app('session')->getId();
        $this->rememberSessionCookie();
        $this->post('/login', [
            'login' => $user->email,
            'password' => 'A-strong-test-password-123!',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertNotSame($before, app('session')->getId());
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $user = $this->user('Administrator', 'inactive');
        $this->postJson('/login', [
            'login' => $user->username,
            'password' => 'A-strong-test-password-123!',
        ])->assertStatus(422)->assertJsonValidationErrors('login');
        $this->assertGuest('web');
    }

    public function test_sixth_failed_attempt_is_throttled_and_the_limit_expires(): void
    {
        $user = $this->user('Administrator');
        $input = ['login' => $user->username, 'password' => 'wrong-password'];
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', $input)->assertStatus(422);
        }
        $this->postJson('/login', $input)->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(61)->seconds();
        $input['password'] = 'A-strong-test-password-123!';
        $this->post('/login', $input)->assertRedirect(route('admin.dashboard'));
    }

    public function test_ip_limit_covers_attempts_across_different_usernames(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->postJson('/login', ['login' => 'missing-' . $i, 'password' => 'wrong'])
                ->assertStatus(422);
        }
        $this->postJson('/login', ['login' => 'another-account', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_disabled_account_loses_an_existing_session(): void
    {
        $user = $this->user('Administrator');
        $this->post('/login', ['login' => $user->username, 'password' => 'A-strong-test-password-123!']);
        $this->rememberSessionCookie();
        $user->update(['status' => 'inactive']);
        $this->getJson('/admin/reports/users')->assertUnauthorized();
    }

    public function test_password_change_invalidates_an_existing_session(): void
    {
        $user = $this->user('Administrator');
        $this->post('/login', ['login' => $user->username, 'password' => 'A-strong-test-password-123!']);
        $this->rememberSessionCookie();
        $user->update(['password' => Hash::make('A-different-test-password!')]);
        $this->getJson('/admin/reports/users')->assertUnauthorized();
        $this->assertGuest('web');
    }

    public function test_revoked_role_takes_effect_on_the_next_request(): void
    {
        $user = $this->user('Administrator');
        $this->post('/login', ['login' => $user->username, 'password' => 'A-strong-test-password-123!']);
        $this->rememberSessionCookie();
        $user->removeRole('Administrator');
        $this->get('/admin/reports/users')->assertForbidden();
    }

    public function test_logged_out_session_cookie_cannot_be_reused(): void
    {
        $user = $this->user('Administrator');
        $this->post('/login', ['login' => $user->username, 'password' => 'A-strong-test-password-123!']);
        $this->rememberSessionCookie();
        $this->post('/logout')->assertRedirect(route('login'));
        $this->resetSessionRuntime();
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_default_seeding_is_repeatable_and_does_not_promote_users(): void
    {
        $user = $this->user('Basic');
        $custom = Permission::findOrCreate('custom.integration', 'web');
        $user->givePermissionTo($custom);
        $before = User::count();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        (new AssignAdminRoleSeeder)->run();
        $this->assertSame($before, User::count());
        $this->assertFalse($user->fresh()->hasRole('Administrator'));
        $this->assertTrue($user->fresh()->hasPermissionTo('custom.integration'));
    }

    public function test_explicit_admin_grant_preserves_existing_roles_and_password(): void
    {
        $user = $this->user('Basic');
        $password = $user->getAuthPassword();
        $this->artisan('admin:grant', ['user_id' => (string) $user->id, '--force' => true])->assertSuccessful();
        $this->artisan('admin:grant', ['user_id' => (string) $user->id, '--force' => true])->assertSuccessful();
        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasAllRoles(['Administrator', 'Basic']));
        $this->assertSame($password, $fresh->getAuthPassword());
    }

    public function test_admin_grant_rejects_inactive_and_missing_accounts(): void
    {
        $user = $this->user('Basic', 'inactive');
        $this->artisan('admin:grant', ['user_id' => (string) $user->id, '--force' => true])->assertFailed();
        $this->artisan('admin:grant', ['user_id' => '999999', '--force' => true])->assertFailed();
        $this->assertFalse($user->fresh()->hasRole('Administrator'));
    }

    public function test_every_existing_admin_route_has_an_explicit_permission_mapping(): void
    {
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() !== 'admin' && !str_starts_with($route->uri(), 'admin/')) {
                continue;
            }
            foreach ($route->methods() as $method) {
                $required = AdminPermissions::required($route->getName(), $method, $route->uri(), 'active');
                $this->assertNotNull($required, $method . ' ' . $route->uri() . ' [' . $route->getName() . ']');
                foreach ($required as $permission) {
                    $this->assertContains($permission, AdminPermissions::all());
                }
                $checked++;
            }
        }
        $this->assertGreaterThan(100, $checked);
    }

    public function test_every_literal_admin_route_reference_exists(): void
    {
        $files = array_merge(
            File::allFiles(resource_path('views/admin')),
            File::allFiles(app_path('Http/Controllers/Admin')),
        );
        $references = [];
        foreach ($files as $file) {
            preg_match_all("/route\\(\\s*['\"](admin\\.[^'\"]+)['\"]/", $file->getContents(), $matches);
            foreach ($matches[1] as $name) {
                $references[$name][] = $file->getRelativePathname();
            }
        }

        $this->assertNotEmpty($references);
        foreach ($references as $name => $locations) {
            $this->assertTrue(Route::has($name), $name . ' referenced by ' . implode(', ', $locations));
        }
    }

    public function test_sensitive_system_mutations_are_rate_limited(): void
    {
        foreach ([
            'admin.system.filemanager.store',
            'admin.system.filemanager.destroy',
            'admin.system.update.cache',
            'admin.system.maintenance.update',
            'admin.system.backups.store',
            'admin.system.backups.download',
            'admin.system.backups.destroy',
            'admin.users.finances.set_overdraft',
            'admin.users.finances.add_remove',
            'admin.users.finances.add_payment',
        ] as $name) {
            $middleware = Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [];
            $this->assertTrue(
                collect($middleware)->contains(fn (string $entry): bool => str_starts_with($entry, 'throttle:')),
                $name . ' must be rate limited',
            );
        }
    }

    public function test_unknown_admin_routes_are_denied_even_for_administrators(): void
    {
        Route::middleware('web')->get('/admin/unreviewed', fn () => 'not allowed')
            ->name('admin.unreviewed.index');
        $this->actingAs($this->user('Administrator'))->get('/admin/unreviewed')->assertForbidden();
    }
}
