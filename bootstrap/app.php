<?php

use App\Http\Middleware\ProtectAdminRoutes;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Never run session authentication in the global, pre-session stack.
        $middleware->web(append: [AuthenticateSession::class, ProtectAdminRoutes::class]);
        // Deny unauthorized access before route model binding can disclose records.
        $middleware->prependToPriorityList(SubstituteBindings::class, ProtectAdminRoutes::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['api_key', 'apiaccesskey', 'secret', 'token']);
    })->create();
