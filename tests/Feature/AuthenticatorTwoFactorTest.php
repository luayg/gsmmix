<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Settings\AppSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SecurityTestCase;

final class AuthenticatorTwoFactorTest extends SecurityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('users',function(Blueprint $table): void {
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_method',20)->default('email');
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->string('google_id')->nullable();
            $table->decimal('balance',12,2)->default(0);
        });
        (require database_path('migrations/2026_09_16_000100_create_settings_table.php'))->up();
    }

    public function test_setup_modal_remains_available_until_the_pending_secret_expires(): void
    {
        $user=$this->user();

        $this->actingAs($user)
            ->withSession(['authenticator_setup_secret'=>[
                'secret'=>self::SECRET,
                'expires_at'=>now()->addMinutes(5)->timestamp,
            ]])
            ->get(route('customer.profile'))
            ->assertOk()
            ->assertSee('Set up authenticator app')
            ->assertSee(self::SECRET);
    }

    public function test_switching_to_email_removes_the_old_authenticator_secret(): void
    {
        $user=$this->authenticatorUser();

        $this->actingAs($user)->put(route('customer.profile.two-factor'),[
            'password'=>'A-strong-test-password-123!',
            'enabled'=>true,
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('email',$user->two_factor_method);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_removing_authenticator_requires_its_current_code(): void
    {
        $user=$this->authenticatorUser();

        $this->actingAs($user)->from(route('customer.profile'))->delete(route('customer.profile.authenticator.remove'),[
            'password'=>'A-strong-test-password-123!',
            'code'=>'000000',
        ])->assertRedirect(route('customer.profile'))->assertSessionHasErrors('code');

        $this->assertSame('authenticator',$user->fresh()->two_factor_method);

        $this->actingAs($user)->delete(route('customer.profile.authenticator.remove'),[
            'password'=>'A-strong-test-password-123!',
            'code'=>$this->currentCode(self::SECRET),
        ])->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
        $this->assertSame('email',$user->two_factor_method);
        $this->assertNull($user->two_factor_secret);
    }

    public function test_administrator_requirement_remains_enabled_after_authenticator_removal(): void
    {
        app(AppSettings::class)->putMany('general',[
            'general.two_factor_enabled'=>['value'=>true,'type'=>'boolean'],
        ]);
        $user=$this->authenticatorUser();

        $this->actingAs($user)->delete(route('customer.profile.authenticator.remove'),[
            'password'=>'A-strong-test-password-123!',
            'code'=>$this->currentCode(self::SECRET),
        ])->assertRedirect();

        $this->assertTrue($user->fresh()->two_factor_enabled);
    }

    public function test_login_uses_authenticator_without_sending_an_email_code(): void
    {
        Mail::fake();
        $user=$this->authenticatorUser();

        $this->post(route('login'),[
            'login'=>$user->username,
            'password'=>'A-strong-test-password-123!',
        ])->assertRedirect(route('two-factor.challenge'))
            ->assertSessionHas('two_factor.method','authenticator');

        $this->assertGuest();
        Mail::assertNothingSent();

        $this->post(route('two-factor.verify'),[
            'code'=>$this->currentCode(self::SECRET),
        ])->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    private function currentCode(string $secret): string
    {
        $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits='';
        foreach(str_split($secret) as $char) {
            $bits.=str_pad(decbin(strpos($alphabet,$char)),5,'0',STR_PAD_LEFT);
        }
        $key='';
        foreach(str_split($bits,8) as $byte) {
            if(strlen($byte)===8) $key.=chr(bindec($byte));
        }
        $counter=intdiv(time(),30);
        $binary=pack('N2',intdiv($counter,4294967296),$counter%4294967296);
        $hash=hash_hmac('sha1',$binary,$key,true);
        $offset=ord($hash[19])&15;
        $value=((ord($hash[$offset])&127)<<24)|((ord($hash[$offset+1])&255)<<16)|((ord($hash[$offset+2])&255)<<8)|(ord($hash[$offset+3])&255);
        return str_pad((string)($value%1000000),6,'0',STR_PAD_LEFT);
    }

    private function authenticatorUser(): User
    {
        $user=$this->user();
        $user->forceFill([
            'two_factor_enabled'=>true,
            'two_factor_method'=>'authenticator',
            'two_factor_secret'=>self::SECRET,
            'two_factor_confirmed_at'=>now(),
        ])->save();
        return $user;
    }
}
