<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class LoginController extends Controller
{
    public function create()
    {
        return response()->view('auth.login')->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request)
    {
        // An IP limit also covers invalid forms and attempts across many usernames.
        $ipKey = 'login:ip:' . hash('sha256', (string) $request->ip());
        $this->checkLimit($ipKey, 25);
        RateLimiter::hit($ipKey, 60);

        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable'], // The existing HTML checkbox submits 'on'.
        ]);

        $login = trim($data['login']);
        $key = 'login:account:' . hash('sha256', Str::lower($login) . '|' . $request->ip());
        $this->checkLimit($key, 5);
        RateLimiter::hit($key, 60);

        foreach (['email', 'username'] as $field) {
            if (Auth::guard('web')->attempt([
                $field => $login,
                'password' => $data['password'],
                'status' => 'active',
            ], $request->boolean('remember'))) {
                RateLimiter::clear($key);
                $request->session()->regenerate();
                $request->session()->put('password_hash_web', Auth::guard('web')->user()->getAuthPassword());
                return redirect()->intended(route('admin.dashboard'));
            }
        }

        // Do not reveal whether the account exists or is inactive.
        throw ValidationException::withMessages(['login' => __('auth.failed')]);
    }

    private function checkLimit(string $key, int $attempts): void
    {
        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            throw new TooManyRequestsHttpException(
                max(1, RateLimiter::availableIn($key)),
                'Too many login attempts. Please try again shortly.',
            );
        }
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
