<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

final class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function toResponse($request)
    {
        $user=$request->user();
        $destination=$user?->can('admin.access')
            ? route('admin.dashboard')
            : route('customer.dashboard');

        if($request->wantsJson()) {
            return new JsonResponse(['redirect'=>redirect()->intended($destination)->getTargetUrl()]);
        }

        return redirect()->intended($destination);
    }
}
