<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class CustomerMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin/*') || $request->is('storage/*') || $request->is('up') || $request->is('payment/webhooks/*')
            || $request->routeIs('login', 'logout', 'password.*')) {
            return $next($request);
        }

        // Administrators may preview the customer storefront while maintenance is enabled.
        if ($request->user()?->can('admin.access')) {
            return $next($request);
        }

        if (! Schema::hasTable('settings') || Setting::where('setting_key', 'system.maintenance')->value('value') !== '1') {
            return $next($request);
        }

        return response()->view('maintenance', [
            'message' => Setting::where('setting_key', 'system.maintenance_message')->value('value'),
            'image' => Setting::where('setting_key', 'system.maintenance_image')->value('value'),
        ], 503, ['Retry-After' => '3600']);
    }
}
