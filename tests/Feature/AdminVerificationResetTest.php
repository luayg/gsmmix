<?php

namespace Tests\Feature;

use App\Models\Passkey;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SecurityTestCase;

final class AdminVerificationResetTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('group_id')->nullable();
            $table->decimal('balance', 12, 2)->default(0);
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_method', 20)->default('email');
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
        (require database_path('migrations/2026_09_17_220000_create_passkeys_table.php'))->up();
    }

    public function test_administrator_can_reset_every_verification_method_for_a_customer(): void
    {
        $administrator = $this->user('Administrator');
        $customer = $this->verifiedUser('Basic');
        $this->passkey($customer->id, 'phone-key');
        $this->passkey($customer->id, 'laptop-key');

        $this->actingAs($administrator)
            ->postJson(route('admin.users.reset_verification', $customer))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $customer->refresh();
        $this->assertFalse($customer->two_factor_enabled);
        $this->assertSame('email', $customer->two_factor_method);
        $this->assertNull($customer->two_factor_secret);
        $this->assertNull($customer->two_factor_confirmed_at);
        $this->assertDatabaseMissing('passkeys', ['user_id' => $customer->id]);
    }

    public function test_administrator_account_can_also_be_reset(): void
    {
        $administrator = $this->user('Administrator');
        $targetAdministrator = $this->verifiedUser('Administrator');
        $this->passkey($targetAdministrator->id, 'admin-key');

        $this->actingAs($administrator)
            ->postJson(route('admin.users.reset_verification', $targetAdministrator))
            ->assertOk();

        $this->assertFalse($targetAdministrator->fresh()->two_factor_enabled);
        $this->assertDatabaseMissing('passkeys', ['user_id' => $targetAdministrator->id]);
    }

    public function test_non_administrator_cannot_reset_verification_even_with_users_edit_permission(): void
    {
        $manager = $this->user('Manager');
        $manager->givePermissionTo('users.edit');
        $target = $this->verifiedUser('Basic');

        $this->actingAs($manager)
            ->postJson(route('admin.users.reset_verification', $target))
            ->assertForbidden();

        $this->assertTrue($target->fresh()->two_factor_enabled);
    }

    public function test_reset_action_is_rendered_for_every_user_row(): void
    {
        $administrator = $this->user('Administrator');
        $customer = $this->user('Basic');

        $response = $this->actingAs($administrator)->getJson(route('admin.users.data'))->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $customer->id);

        $this->assertNotNull($row);
        $this->assertStringContainsString(route('admin.users.modal.reset_verification', $customer), $row['actions']);
    }

    private function verifiedUser(string $role)
    {
        $user = $this->user($role);
        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_method' => 'authenticator',
            'two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'two_factor_confirmed_at' => now(),
        ])->save();
        return $user;
    }

    private function passkey(int $userId, string $credentialId): void
    {
        Passkey::create([
            'user_id' => $userId,
            'name' => $credentialId,
            'credential_id' => $credentialId,
            'credential' => '{}',
        ]);
    }
}
