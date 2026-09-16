<?php

use App\Http\Middleware\ProtectAdminRoutes;
use App\Http\Middleware\RecordAdminActivity;
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
        $middleware->validateCsrfTokens(except: ['payment/webhooks/*']);
        // Never run session authentication in the global, pre-session stack.
        $middleware->web(append: [AuthenticateSession::class, ProtectAdminRoutes::class, RecordAdminActivity::class]);
        // Deny unauthorized access before route model binding can disclose records.
        $middleware->prependToPriorityList(SubstituteBindings::class, ProtectAdminRoutes::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'api_key', 'api_secret', 'webhook_secret', 'client_secret', 'apiaccesskey', 'secret', 'token']);
    })->create();
