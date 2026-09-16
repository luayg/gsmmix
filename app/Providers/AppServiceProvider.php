<?php

namespace App\Providers;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\FileService;
use App\Models\ImeiOrder;
use App\Models\ImeiService;
use App\Models\ServerOrder;
use App\Models\ServerService;
use App\Models\SmmOrder;
use App\Models\SmmService;
use App\Models\User;
use App\Observers\FileOrderGroupPricingObserver;
use App\Observers\OrderProviderMetadataSanitizerObserver;
use App\Observers\OrderStatusFinanceObserver;
use App\Observers\ProviderDeletionFallbackObserver;
use App\Observers\ServerOrderQuantityBillingObserver;
use App\Observers\ServiceDeletionGuardObserver;
use App\Support\AdminPermissions;
use App\Services\Settings\AppSettings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;
use App\Models\Page;
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
        app(AppSettings::class)->applyRuntimeConfiguration();

        View::composer(['layouts.site','layouts.customer'], function ($view): void {
            if (!Schema::hasTable('pages')) {
                $view->with('headerPages', collect())->with('footerPages', collect());
                return;
            }
            $pages = Page::query()->where('status','published')->where(function($q){$q->whereNull('published_at')->orWhere('published_at','<=',now());})
                ->when(!auth()->check(),fn($q)=>$q->where('authenticated_only',false))->with('translations')->orderBy('ordering')->get();
            $view->with('headerPages',$pages->where('placement','header'))->with('footerPages',$pages->where('placement','footer'));
        });

        Route::aliasMiddleware('role', RoleMiddleware::class);
        Route::aliasMiddleware('permission', PermissionMiddleware::class);
        Route::aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);

        ApiProvider::observe(ProviderDeletionFallbackObserver::class);

        ServerOrder::observe(ServerOrderQuantityBillingObserver::class);
        FileOrder::observe(FileOrderGroupPricingObserver::class);
        foreach ([ImeiOrder::class, ServerOrder::class, FileOrder::class, SmmOrder::class] as $orderModel) {
            $orderModel::observe(OrderProviderMetadataSanitizerObserver::class);
            $orderModel::observe(OrderStatusFinanceObserver::class);
        }

        foreach ([ImeiService::class, ServerService::class, FileService::class, SmmService::class] as $serviceModel) {
            $serviceModel::observe(ServiceDeletionGuardObserver::class);
        }

        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->status !== 'active') {
                return false;
            }
            return $user->hasRole(AdminPermissions::ADMIN_ROLE, 'web') ? true : null;
        });
    }
}
