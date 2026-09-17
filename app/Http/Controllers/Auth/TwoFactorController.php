<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\LoginFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Services\Auth\Totp;

final class TwoFactorController extends Controller
{
    public function create(Request $request)
    {
        abort_unless($request->session()->has('two_factor'), 404);
        return response()->view('auth.two-factor')->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, LoginFlow $flow, Totp $totp)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $challenge = $request->session()->get('two_factor');
        if (!$challenge || ($challenge['expires_at'] ?? 0) < now()->timestamp || ($challenge['attempts'] ?? 0) >= 5) {
            $request->session()->forget('two_factor');
            throw ValidationException::withMessages(['code' => 'The verification code has expired. Please sign in again.']);
        }
        $challenge['attempts']++;
        $request->session()->put('two_factor', $challenge);
        $user = User::query()->whereKey($challenge['user_id'])->where('status', 'active')->firstOrFail();
        $valid=($challenge['method']??'email')==='authenticator'
            ? filled($user->two_factor_secret) && $totp->verify($user->two_factor_secret,$data['code'])
            : isset($challenge['code']) && Hash::check($data['code'], $challenge['code']);
        if (!$valid) {
            throw ValidationException::withMessages(['code' => 'The verification code is incorrect.']);
        }
        $request->session()->forget('two_factor');
        return $flow->login($request, $user, (bool) ($challenge['remember'] ?? false));
    }
}
