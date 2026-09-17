<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class AppSettings
{
    private const CACHE_PREFIX = 'app_settings.group.';

    public function applyRuntimeConfiguration(): void
    {
        try {
            if (!Schema::hasTable('settings')) {
                return;
            }
            $general = $this->group('general');
            $mail = $this->group('mail');
            if ($general !== []) {
                config([
                    'app.name' => $general['general.site_name'] ?? config('app.name'),
                    'app.timezone' => $general['general.timezone'] ?? config('app.timezone'),
                    'session.lifetime' => $general['general.session_lifetime'] ?? config('session.lifetime'),
                    'session.expire_on_close' => $general['general.session_expire_on_close'] ?? config('session.expire_on_close'),
                ]);
            }
            if (Schema::hasTable('languages')) {
                $locale = DB::table('languages')->where('is_default', true)->where('active', true)->value('locale');
                if (is_string($locale) && $locale !== '') {
                    config(['app.locale' => $locale]);
                    app()->setLocale($locale);
                }
            }
            if ($mail !== []) {
                config([
                    'mail.default' => $mail['mail.mailer'] ?? config('mail.default'),
                    'mail.mailers.smtp.host' => $mail['mail.host'] ?? null,
                    'mail.mailers.smtp.port' => $mail['mail.port'] ?? null,
                    'mail.mailers.smtp.scheme' => ($mail['mail.encryption'] ?? null) === 'ssl' ? 'smtps' : null,
                    'mail.mailers.smtp.username' => $mail['mail.username'] ?? null,
                    'mail.mailers.smtp.password' => $mail['mail.password'] ?? null,
                    'mail.mailers.smtp.timeout' => $mail['mail.timeout'] ?? null,
                    'mail.mailers.sendmail.path' => $mail['mail.sendmail_path'] ?? config('mail.mailers.sendmail.path'),
                    'mail.mailers.log.channel' => $mail['mail.log_channel'] ?? config('mail.mailers.log.channel'),
                    'mail.from.address' => $mail['mail.from_address'] ?? config('mail.from.address'),
                    'mail.from.name' => $mail['mail.from_name'] ?? config('mail.from.name'),
                ]);
            }
            if (isset($general['general.timezone'])) {
                date_default_timezone_set($general['general.timezone']);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function group(string $group): array
    {
        try {
            if (!Schema::hasTable('settings')) {
                return [];
            }
        } catch (Throwable $exception) {
            report($exception);
            return [];
        }
        $load = function () use ($group): array {
            return Setting::query()->where('group_name', $group)->get()->mapWithKeys(
                fn (Setting $setting) => [$setting->setting_key => $this->decode($setting)]
            )->all();
        };
        // Never persist decrypted credentials in a shared cache backend.
        // Authentication and mail groups may contain decrypted credentials.
        return in_array($group, ['mail', 'auth'], true) ? $load() : Cache::rememberForever(self::CACHE_PREFIX.$group, $load);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        try {
            if (!Schema::hasTable('settings')) {
                return $default;
            }
            $setting = Setting::query()->where('setting_key', $key)->first();
            return $setting ? $this->decode($setting) : $default;
        } catch (Throwable $exception) {
            report($exception);
            return $default;
        }
    }

    /** @param array<string, array{value:mixed,type?:string,encrypted?:bool}> $values */
    public function putMany(string $group, array $values): void
    {
        DB::transaction(function () use ($group, $values): void {
            foreach ($values as $key => $definition) {
                $type = $definition['type'] ?? 'string';
                $encrypted = (bool) ($definition['encrypted'] ?? false);
                $value = $this->encode($definition['value'], $type);
                if ($encrypted && $value !== null) {
                    $value = Crypt::encryptString($value);
                }
                Setting::query()->updateOrCreate(
                    ['setting_key' => $key],
                    ['group_name' => $group, 'value' => $value, 'value_type' => $type, 'is_encrypted' => $encrypted]
                );
            }
        });
        if (!in_array($group, ['mail', 'auth'], true)) {
            Cache::forget(self::CACHE_PREFIX.$group);
        }
    }

    private function decode(Setting $setting): mixed
    {
        $value = $setting->getRawOriginal('value');
        if ($setting->is_encrypted && $value !== null) {
            $value = Crypt::decryptString($value);
        }
        if ($value === null) {
            return null;
        }
        return match ($setting->value_type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $value,
            'decimal' => (string) $value,
            'json' => $value === null ? null : json_decode($value, true, flags: JSON_THROW_ON_ERROR),
            default => $value,
        };
    }

    private function encode(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) ((int) $value),
            'json' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }
}
