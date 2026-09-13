<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\Support\SecurityTestCase;

class AdminSessionRegressionTest extends SecurityTestCase
{
    private function authenticatedJsonSession(): User
    {
        $user = $this->user('Administrator');
        $this->post('/login', [
            'login' => $user->username,
            'password' => 'A-strong-test-password-123!',
        ])->assertRedirect(route('admin.dashboard'));
        $this->rememberSessionCookie();

        // Positive control: the JSON request really carries a valid session cookie.
        $this->getJson('/admin/reports/users')->assertOk();
        $this->assertAuthenticatedAs($user, 'web');
        $this->rememberSessionCookie();
        return $user;
    }

    public function test_json_cookie_session_is_rejected_after_account_is_disabled(): void
    {
        $user = $this->authenticatedJsonSession();
        $user->update(['status' => 'inactive']);
        $this->getJson('/admin/reports/users')->assertUnauthorized();
        $this->assertGuest('web');
    }

    public function test_json_cookie_session_is_rejected_after_password_changes(): void
    {
        $user = $this->authenticatedJsonSession();
        $user->update(['password' => 'A-new-unrelated-test-password!']);
        $this->getJson('/admin/reports/users')->assertUnauthorized();
        $this->assertGuest('web');
    }

    public function test_json_cookie_session_loses_access_after_role_revocation(): void
    {
        $user = $this->authenticatedJsonSession();
        $user->removeRole('Administrator');
        $this->getJson('/admin/reports/users')->assertForbidden();
    }

    public function test_administrator_can_use_all_reviewed_unnamed_redirects(): void
    {
        $this->actingAs($this->user('Administrator'));
        $this->get('/admin')->assertRedirect(route('admin.dashboard'));
        $this->get('/admin/api')->assertRedirect(route('admin.apis.index'));
        foreach (['imei', 'server', 'file', 'smm', 'groups'] as $kind) {
            $this->get('/admin/services/' . $kind)
                ->assertRedirect(route('admin.services.' . $kind . '.index'));
        }
    }

    public function test_basic_customer_cannot_use_admin_redirects(): void
    {
        $this->actingAs($this->user('Basic'));
        foreach (['/admin', '/admin/api', '/admin/services/imei'] as $uri) {
            $this->get($uri)->assertForbidden();
        }
    }

    public function test_unknown_route_with_only_an_inherited_name_is_still_denied(): void
    {
        Route::middleware('web')->prefix('admin')->name('admin.')->group(function (): void {
            Route::get('/not-reviewed', fn () => response('not allowed'));
        });
        $this->actingAs($this->user('Administrator'))->get('/admin/not-reviewed')->assertForbidden();
    }
}
