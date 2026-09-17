<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Passkeys\Passkey;
use Tests\Support\SecurityTestCase;

final class PasskeyManagementTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('users',function(Blueprint $table): void {
            $table->decimal('balance',12,2)->default(0);
            $table->string('google_id')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_method',20)->default('email');
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
        (require database_path('migrations/2026_09_17_220000_create_passkeys_table.php'))->up();
    }

    public function test_guest_cannot_use_passkey_management_routes(): void
    {
        $this->get(route('passkey.registration-options'))->assertRedirect(route('login'));
        $this->post(route('passkey.store'))->assertRedirect(route('login'));
    }

    public function test_profile_lists_only_the_authenticated_users_passkeys(): void
    {
        $user=$this->user();
        $other=$this->user();
        $this->passkey($user->id,'Office PC','credential-office');
        $this->passkey($other->id,'Other account key','credential-other');

        $this->actingAs($user)->get(route('customer.profile'))
            ->assertOk()
            ->assertSee('Office PC')
            ->assertDontSee('Other account key')
            ->assertSee('Add passkey');
    }

    public function test_user_can_delete_own_passkey_but_not_another_users_key(): void
    {
        $user=$this->user();
        $other=$this->user();
        $own=$this->passkey($user->id,'My phone','credential-phone');
        $foreign=$this->passkey($other->id,'Other key','credential-foreign');

        $this->actingAs($user)->deleteJson(route('passkey.destroy',$foreign))->assertForbidden();
        $this->assertDatabaseHas('passkeys',['id'=>$foreign->id]);

        $this->deleteJson(route('passkey.destroy',$own))->assertOk();
        $this->assertDatabaseMissing('passkeys',['id'=>$own->id]);
    }

    private function passkey(int $userId,string $name,string $credentialId): Passkey
    {
        $passkey=new Passkey([
            'name'=>$name,
            'credential_id'=>$credentialId,
            'credential'=>['publicKey'=>'test'],
        ]);
        $passkey->user_id=$userId;
        $passkey->save();
        return $passkey;
    }
}
