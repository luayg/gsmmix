<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // لا ترجع أي شيء عند طلبات JSON (API)
        if ($request->expectsJson()) {
            return null;
        }

        // مسار تسجيل الدخول المعرّف بالاسم 'login'
        return route('login');
    }
}
