<?php

namespace App\Http\Middleware;

use App\Support\AdminPermissions;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProtectAdminRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->is('admin', 'admin/*') && !$request->routeIs('admin.*')) {
            return $next($request);
        }

        // This middleware belongs to the web group, after StartSession.
        $user = $request->user('web');
        if (!$user) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
        if ($user->status !== 'active') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }

        $gate = Gate::forUser($user);
        abort_unless($gate->allows('admin.access'), 403);

        $name = $request->route()?->getName();
        $action = $request->input('action');
        $required = AdminPermissions::required(
            $name,
            $request->method(),
            $request->path(),
            is_string($action) ? $action : null,
        );
        abort_if($required === null, 403);

        if (AdminPermissions::administratorOnly($name)) {
            abort_unless($user->hasRole(AdminPermissions::ADMIN_ROLE, 'web'), 403);
        }
        foreach ($required as $permission) {
            abort_unless($gate->allows($permission), 403);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        return $response;
    }
}
