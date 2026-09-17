<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Services\Auth\LoginFlow;
use App\Services\Settings\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\FinanceAccount;

final class GoogleController extends Controller
{
    public function __construct(private readonly AppSettings $settings) {}

    public function redirect(Request $request)
    {
        abort_unless($this->enabled(), 404);
        $state = Str::random(64);
        $request->session()->put('google_oauth_state', $state);
        $query = http_build_query([
            'client_id' => $this->settings->get('auth.google_client_id'),
            'redirect_uri' => route('auth.google.callback'),
            'response_type' => 'code', 'scope' => 'openid email profile',
            'state' => $state, 'prompt' => 'select_account',
        ]);
        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request, LoginFlow $flow)
    {
        abort_unless($this->enabled(), 404);
        $data = $request->validate(['code' => ['required', 'string'], 'state' => ['required', 'string']]);
        if (!hash_equals((string) $request->session()->pull('google_oauth_state'), $data['state'])) {
            throw ValidationException::withMessages(['login' => 'The Google sign-in request expired. Please try again.']);
        }
        $token = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->settings->get('auth.google_client_id'),
            'client_secret' => $this->settings->get('auth.google_client_secret'),
            'code' => $data['code'], 'grant_type' => 'authorization_code',
            'redirect_uri' => route('auth.google.callback'),
        ])->throw()->json('access_token');
        $profile = Http::withToken($token)->timeout(15)->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
        if (!($profile['email_verified'] ?? false) || blank($profile['email'] ?? null) || blank($profile['sub'] ?? null)) {
            throw ValidationException::withMessages(['login' => 'Google did not provide a verified email address.']);
        }

        $email = Str::lower($profile['email']);
        $user = User::query()->where('google_id', $profile['sub'])->orWhere('email', $email)->first();
        if (!$user) {
            abort_unless((bool) $this->settings->get('general.registration_enabled', false), 403, 'Public registration is disabled.');
            $base = Str::slug(Str::before($email, '@'), '_') ?: 'user';
            $username = $base;
            for ($i = 1; User::where('username', $username)->exists(); $i++) $username = $base.$i;
            $user = User::create([
                'name' => $profile['name'] ?? $email, 'username' => $username, 'email' => $email,
                'email_verified_at' => now(), 'password' => Str::random(48), 'google_id' => $profile['sub'],
                'status' => 'active', 'balance' => 0,
                'group_id' => $this->settings->get('general.default_group_id') ?: Group::query()->orderBy('id')->value('id'),
            ]);
            FinanceAccount::query()->firstOrCreate(['user_id'=>$user->id],['locked_amount'=>0,'total_receipts'=>0,'paid_credits'=>0,'overdraft_limit'=>$this->settings->get('general.default_overdraft','0')]);
        } elseif ($user->status !== 'active') {
            throw ValidationException::withMessages(['login' => 'This account is not active.']);
        } elseif (!$user->google_id) {
            $user->forceFill(['google_id' => $profile['sub'], 'email_verified_at' => $user->email_verified_at ?: now()])->save();
        }
        return $flow->completeOrChallenge($request, $user);
    }

    private function enabled(): bool
    {
        return (bool) $this->settings->get('general.google_login_enabled', false)
            && filled($this->settings->get('auth.google_client_id'))
            && filled($this->settings->get('auth.google_client_secret'));
    }
}
