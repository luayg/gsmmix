<?php

namespace App\Services\Settings;

use App\Models\Language;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ContentTranslator
{
    public function get(string $key, ?string $locale = null, ?string $fallback = null): string
    {
        $locale ??= app()->getLocale();
        try {
            if (! Schema::hasTable('languages') || ! Schema::hasTable('language_translations')) {
                return (string) ($fallback ?? $key);
            }
            $lines = Cache::rememberForever('content_translations.'.$locale, function () use ($locale): array {
                $language = Language::query()->where('locale', $locale)->where('active', true)->first();
                return $language ? $language->translations()->pluck('value', 'translation_key')->all() : [];
            });
            return (string) ($lines[$key] ?? $fallback ?? $key);
        } catch (Throwable $exception) {
            report($exception);
            return (string) ($fallback ?? $key);
        }
    }

    public function forget(Language $language): void
    {
        $this->forgetLocale($language->locale);
    }

    public function forgetLocale(string $locale): void
    {
        Cache::forget('content_translations.'.$locale);
    }
}
