<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Settings\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

final class LoginFlow
{
    public function __construct(private readonly AppSettings $settings) {}

    public function completeOrChallenge(Request $request, User $user, bool $remember = false)
    {
        $mandatory = (bool) $this->settings->get('general.two_factor_enabled', false);
        if ($mandatory || $user->two_factor_enabled) {
            Auth::guard('web')->logout();
            $authenticator=$user->two_factor_method==='authenticator' && filled($user->two_factor_secret) && $user->two_factor_confirmed_at;
            $challenge = [
                'user_id' => $user->id,
                'remember' => $remember,
                'method' => $authenticator ? 'authenticator' : 'email',
                'expires_at' => now()->addMinutes(10)->timestamp,
                'attempts' => 0,
            ];
            if(!$authenticator){
                $code = (string) random_int(100000, 999999);
                $challenge['code']=Hash::make($code);
                Mail::raw("Your verification code is {$code}. It expires in 10 minutes.", function ($message) use ($user): void {
                    $message->to($user->email)->subject('Your sign-in verification code');
                });
            }
            $request->session()->put('two_factor',$challenge);
            return redirect()->route('two-factor.challenge');
        }

        return $this->login($request, $user, $remember);
    }

    public function login(Request $request, User $user, bool $remember = false)
    {
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', $user->getAuthPassword());
        $destination = $user->can('admin.access') ? route('admin.dashboard') : route('customer.dashboard');
        return redirect()->intended($destination);
    }
}
