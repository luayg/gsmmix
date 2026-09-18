<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\Auth\LoginFlow;

class LoginController extends Controller
{
    public function create()
    {
        return response()->view('auth.login')->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, LoginFlow $flow)
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
                RateLimiter::clear($ipKey);
                $this->logAccess($request,'login',true,$login,Auth::guard('web')->id());
                $user = Auth::guard('web')->user();
                return $flow->completeOrChallenge($request, $user, $request->boolean('remember'));
            }
        }

        // Do not reveal whether the account exists or is inactive.
        $this->logAccess($request,'login',false,$login,null,'invalid_credentials_or_inactive');
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
        $this->logAccess($request,'logout',true,'',$request->user()?->id);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home')->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Clear-Site-Data' => '"cache"',
        ]);
    }
    private function logAccess(Request $r,string $event,bool $ok,string $identity,?int $userId,?string $reason=null):void{if(!Schema::hasTable('access_logs'))return;$row=['user_id'=>$userId,'event'=>$event,'successful'=>$ok,'identity_hash'=>$identity!==''?hash('sha256',Str::lower($identity).config('app.key')):null,'ip_hash'=>hash('sha256',(string)$r->ip().config('app.key')),'user_agent'=>Str::limit((string)$r->userAgent(),1000),'session_hash'=>$r->hasSession()?hash('sha256',$r->session()->getId().config('app.key')):null,'reason'=>$reason,'occurred_at'=>now()];if(Schema::hasColumn('access_logs','ip_address'))$row['ip_address']=$r->ip();DB::table('access_logs')->insert($row);}
}
