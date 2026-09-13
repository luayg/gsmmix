<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ProtectAdminRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        if (($request->is('admin') || $request->is('admin/*')) && ! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
