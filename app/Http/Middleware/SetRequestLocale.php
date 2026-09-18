<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class SetRequestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('languages')) {
            return $next($request);
        }

        $locale = $request->session()->get('locale');
        $language = is_string($locale)
            ? Language::query()->where('locale', $locale)->where('active', true)->first()
            : null;

        $language ??= Language::query()
            ->where('is_default', true)
            ->where('active', true)
            ->first();

        if ($language) {
            app()->setLocale($language->locale);
            config(['app.locale' => $language->locale]);
            $request->session()->put('locale', $language->locale);
        }

        return $next($request);
    }
}
