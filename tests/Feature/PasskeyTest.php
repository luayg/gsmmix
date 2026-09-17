<?php

namespace Tests\Feature;

use App\Models\Passkey;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SecurityTestCase;

final class PasskeyTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('users', function (Blueprint $table): void {
            $table->string('google_id')->nullable();
        });
        (require database_path('migrations/2026_09_17_220000_create_passkeys_table.php'))->up();
    }

    public function test_login_options_are_usernameless_and_require_user_verification(): void
    {
        $response = $this->getJson(route('passkeys.login.options'))->assertOk();

        $response->assertJsonPath('options.rpId', 'localhost');
        $response->assertJsonPath('options.userVerification', 'required');
        $response->assertJsonMissingPath('options.allowCredentials.0');
        $response->assertSessionHas('passkey_login.options');
    }

    public function test_registration_options_require_a_resident_key_and_user_verification(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->getJson(route('customer.profile.passkeys.options'))->assertOk();

        $response->assertJsonPath('options.authenticatorSelection.residentKey', 'required');
        $response->assertJsonPath('options.authenticatorSelection.userVerification', 'required');
        $response->assertSessionHas('passkey_registration.options');
    }

    public function test_passkey_deletion_requires_owner_and_current_password(): void
    {
        $owner = $this->user();
        $passkey = Passkey::create([
            'user_id' => $owner->id,
            'name' => 'Test key',
            'credential_id' => 'credential-id',
            'credential' => '{}',
        ]);

        $this->actingAs($owner)->deleteJson(route('customer.profile.passkeys.destroy', $passkey), [
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->actingAs($owner)->deleteJson(route('customer.profile.passkeys.destroy', $passkey), [
            'password' => 'A-strong-test-password-123!',
        ])->assertOk();
        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);

    }

    public function test_a_user_cannot_delete_another_users_passkey(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $otherKey = Passkey::create([
            'user_id' => $owner->id,
            'name' => 'Another key',
            'credential_id' => 'another-credential-id',
            'credential' => '{}',
        ]);
        $this->actingAs($other)->deleteJson(route('customer.profile.passkeys.destroy', $otherKey), [
            'password' => 'A-strong-test-password-123!',
        ])->assertNotFound();
    }

    public function test_a_login_challenge_is_consumed_after_one_submission(): void
    {
        $this->withSession(['passkey_login' => [
            'options' => '{}',
            'expires_at' => now()->subSecond()->timestamp,
        ]])->postJson(route('passkeys.login'), [
            'credential' => ['id' => 'invalid'],
            'remember' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('credential');

        $this->assertFalse(session()->has('passkey_login'));
    }
}
