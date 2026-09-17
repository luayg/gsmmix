<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Passkey;
use App\Services\Auth\LoginFlow;
use App\Services\Auth\PasskeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PasskeyController extends Controller
{
    public function loginOptions(Request $request, PasskeyService $passkeys): JsonResponse
    {
        $options = $passkeys->authenticationOptions($request->getHost());
        $request->session()->put('passkey_login', [
            'options' => $passkeys->serialize($options),
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]);

        return response()->json(['options' => json_decode($passkeys->serialize($options), true, flags: JSON_THROW_ON_ERROR)]);
    }

    public function login(Request $request, PasskeyService $passkeys, LoginFlow $flow): JsonResponse
    {
        $data = $request->validate(['credential' => ['required', 'array'], 'remember' => ['nullable', 'boolean']]);
        $challenge = $request->session()->pull('passkey_login');
        if (! is_array($challenge) || ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['credential' => 'The passkey request expired. Please try again.']);
        }

        try {
            $passkey = $passkeys->authenticate(json_encode($data['credential'], JSON_THROW_ON_ERROR), $passkeys->requestOptions($challenge['options']), $request->getHost());
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['credential' => 'This passkey could not be verified.']);
        }

        $response = $flow->login($request, $passkey->user, $request->boolean('remember'));

        return response()->json(['redirect' => $response->getTargetUrl()]);
    }

    public function registrationOptions(Request $request, PasskeyService $passkeys): JsonResponse
    {
        $options = $passkeys->registrationOptions($request->user()->load('passkeys'), $request->getHost());
        $request->session()->put('passkey_registration', [
            'options' => $passkeys->serialize($options),
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]);

        return response()->json(['options' => json_decode($passkeys->serialize($options), true, flags: JSON_THROW_ON_ERROR)]);
    }

    public function store(Request $request, PasskeyService $passkeys): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'credential' => ['required', 'array']]);
        $challenge = $request->session()->pull('passkey_registration');
        if (! is_array($challenge) || ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['credential' => 'The passkey request expired. Please try again.']);
        }

        try {
            $passkeys->register($request->user(), $data['name'], json_encode($data['credential'], JSON_THROW_ON_ERROR), $passkeys->creationOptions($challenge['options']), $request->getHost());
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['credential' => 'The passkey could not be saved. It may already be registered.']);
        }

        return response()->json(['message' => 'Passkey added.']);
    }

    public function destroy(Request $request, Passkey $passkey): JsonResponse
    {
        abort_unless($passkey->user_id === $request->user()->id, 404);
        $request->validate(['password' => $request->user()->google_id ? ['nullable', 'string'] : ['required', 'current_password:web']]);
        $passkey->delete();

        return response()->json(['message' => 'Passkey removed.']);
    }
}
