<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\LoginFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class TwoFactorController extends Controller
{
    public function create(Request $request)
    {
        abort_unless($request->session()->has('two_factor'), 404);
        return response()->view('auth.two-factor')->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, LoginFlow $flow)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $challenge = $request->session()->get('two_factor');
        if (!$challenge || ($challenge['expires_at'] ?? 0) < now()->timestamp || ($challenge['attempts'] ?? 0) >= 5) {
            $request->session()->forget('two_factor');
            throw ValidationException::withMessages(['code' => 'The verification code has expired. Please sign in again.']);
        }
        $challenge['attempts']++;
        $request->session()->put('two_factor', $challenge);
        if (!Hash::check($data['code'], $challenge['code'])) {
            throw ValidationException::withMessages(['code' => 'The verification code is incorrect.']);
        }
        $user = User::query()->whereKey($challenge['user_id'])->where('status', 'active')->firstOrFail();
        $request->session()->forget('two_factor');
        return $flow->login($request, $user, (bool) ($challenge['remember'] ?? false));
    }
}
