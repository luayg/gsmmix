<?php

namespace App\Providers;

use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::aliasMiddleware('role', RoleMiddleware::class);
        Route::aliasMiddleware('permission', PermissionMiddleware::class);
        Route::aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);

        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->status !== 'active') {
                return false;
            }
            return $user->hasRole(AdminPermissions::ADMIN_ROLE, 'web') ? true : null;
        });
    }
}
