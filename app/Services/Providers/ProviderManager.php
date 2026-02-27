<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use App\Models\FileService;
use App\Models\ImeiService;
use App\Models\ServerService;
use Illuminate\Support\Facades\Log;

class ProviderManager
{
    public function __construct(private ProviderFactory $factory)
    {
    }

    public function sync(ApiProvider $provider, ?string $onlyKind = null, bool $balanceOnly = false): array
    {
        $result = [
            'provider_id' => $provider->id,
            'type' => $provider->type,
            'balance' => null,
            'synced' => false,
            'catalog' => [],
            'errors' => [],
            'warnings' => [],
        ];

        if ((int)$provider->active !== 1) {
            $result['errors'][] = 'PROVIDER DISABLED';
            $provider->synced = 0;
            $provider->save();
            $result['synced'] = false;
            return $result;
        }

        $adapter = $this->factory->make($provider);

        // 1) Balance first — ALWAYS try
        try {
            $balance = (float) $adapter->fetchBalance($provider);
            $provider->balance = $balance;
            $provider->save();
            $result['balance'] = $balance;
        } catch (\Throwable $e) {
            $short = $this->shortProviderError($e);
            $this->addUniqueError($result['errors'], $short);

            Log::warning('Fetch balance failed', [
                'provider_id' => $provider->id,
                'type' => $provider->type,
                'error_class' => get_class($e),
                'error' => $e->getMessage(),
            ]);
        }

        if ($balanceOnly) {
            // synced YES only if no errors
            $provider->synced = empty($result['errors']) ? 1 : 0;
            $provider->save();
            $this->refreshStats($provider);
            $result['synced'] = (bool) $provider->synced;
            return $result;
        }

        // 2) Determine kinds
        $kinds = [];
        if ($onlyKind) {
            $kinds[] = $onlyKind;
        } else {
            if ($provider->sync_imei)   $kinds[] = 'imei';
            if ($provider->sync_server) $kinds[] = 'server';
            if ($provider->sync_file)   $kinds[] = 'file';
        }

        foreach ($kinds as $kind) {
            try {
                $count = $adapter->syncCatalog($provider, $kind);
                $result['catalog'][$kind] = ['ok' => true, 'count' => (int)$count];
            } catch (\Throwable $e) {
                $short = $this->shortProviderError($e);

                // FILE not active -> warning only
                if ($kind === 'file' && $this->isNoFileServiceActive($short)) {
                    $result['warnings'][] = 'NO FILE SERVICE ACTIVE';
                    $result['catalog'][$kind] = ['ok' => false, 'count' => 0, 'note' => 'skipped'];

                    $provider->sync_file = 0;
                    $provider->save();
                    continue;
                }

                $result['catalog'][$kind] = ['ok' => false, 'count' => 0, 'error' => $short];
                $this->addUniqueError($result['errors'], $short);

                Log::error('Catalog sync failed', [
                    'provider_id' => $provider->id,
                    'type' => $provider->type,
                    'kind' => $kind,
                    'error_class' => get_class($e),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ✅ Synced = YES only when NO errors
        $provider->synced = empty($result['errors']) ? 1 : 0;
        $provider->save();

        $this->refreshStats($provider);

        $result['synced'] = (bool) $provider->synced;
        $result['balance'] = (float) $provider->balance;

        return $result;
    }

    /**
     * Keep errors list minimal:
     * - Prefer: IP BLOCKED / INVALID URL / AUTH FAILED
     * - Otherwise: PROVIDER DOWN / TIMEOUT / PROVIDER ERROR
     */
    private function addUniqueError(array &$errors, string $msg): void
    {
        $msg = trim($msg);
        if ($msg === '') return;

        $priority = [
            'IP BLOCKED - Reset Provider IP',
            'INVALID URL - Check provider URL/api_path',
            'AUTH FAILED - Check username/api_key/auth_mode',
            'TIMEOUT - Provider not responding',
            'PROVIDER DOWN - Try again later',
            'PROVIDER ERROR',
        ];

        // If a high-priority message exists, do not add lower ones
        foreach ($priority as $p) {
            if (in_array($p, $errors, true)) {
                // If we already have top-tier, keep it only
                if ($p === $priority[0]) return;
                // If adding higher tier, replace later
            }
        }

        // If adding a priority message, keep only that one
        if (in_array($msg, $priority, true)) {
            // if IP BLOCKED -> keep only it
            if ($msg === 'IP BLOCKED - Reset Provider IP') {
                $errors = [$msg];
                return;
            }

            // If we already have IP BLOCKED, keep it
            if (in_array('IP BLOCKED - Reset Provider IP', $errors, true)) return;

            // Otherwise keep a single best message
            $errors = [$msg];
            return;
        }

        // fallback: keep only one
        if (empty($errors)) $errors[] = $msg;
    }

    /**
     * Short, strict English errors:
     * - IP BLOCKED / INVALID URL / AUTH FAILED => strict
     * - TIMEOUT / PROVIDER DOWN => retryable
     * - else => PROVIDER ERROR
     */
    private function shortProviderError(\Throwable $e): string
    {
        $msg = trim((string)$e->getMessage());
        $m = strtolower($msg);

        // Detect HTTP status if embedded in message
        $status = 0;
        if (preg_match('/\bhttp\s*([0-9]{3})\b/i', $msg, $mm)) $status = (int)$mm[1];
        if ($status === 0 && preg_match('/\bstatus\s*code\s*([0-9]{3})\b/i', $msg, $mm)) $status = (int)$mm[1];

        // INVALID URL / DNS
        if (
            str_contains($m, 'could not resolve') ||
            str_contains($m, 'name or service not known') ||
            str_contains($m, 'no such host') ||
            str_contains($m, 'invalid url') ||
            str_contains($m, 'malformed') ||
            str_contains($m, 'cURL error 3') ||
            str_contains($m, 'cURL error 6')
        ) {
            return 'INVALID URL - Check provider URL/api_path';
        }

        // AUTH
        if (
            $status === 401 || $status === 403 ||
            str_contains($m, 'unauthorized') ||
            str_contains($m, 'forbidden') ||
            (str_contains($m, 'auth') && str_contains($m, 'fail')) ||
            str_contains($m, 'invalid key') ||
            (str_contains($m, 'api key') && str_contains($m, 'invalid'))
        ) {
            return 'AUTH FAILED - Check username/api_key/auth_mode';
        }

        // IP BLOCKED / WAF / HTML 503 pages
        if (
            str_contains($m, 'ip blocked') ||
            str_contains($m, 'whitelist') ||
            str_contains($m, 'access denied') ||
            str_contains($m, 'cloudflare') ||
            str_contains($m, '<!doctype html') ||
            str_contains($m, '<html') ||
            $status === 503
        ) {
            return 'IP BLOCKED - Reset Provider IP';
        }

        // TIMEOUT / connection
        if (
            str_contains($m, 'timed out') ||
            str_contains($m, 'timeout') ||
            str_contains($m, 'connection refused') ||
            str_contains($m, 'failed to connect') ||
            str_contains($m, 'cURL error 7') ||
            str_contains($m, 'cURL error 28')
        ) {
            return 'TIMEOUT - Provider not responding';
        }

        // PROVIDER DOWN
        if ($status >= 500) {
            return 'PROVIDER DOWN - Try again later';
        }

        return 'PROVIDER ERROR';
    }

    private function isNoFileServiceActive(string $msg): bool
    {
        $m = strtolower($msg);
        return str_contains($m, 'no file service active')
            || str_contains($m, 'file service active')
            || str_contains($m, 'file service not active');
    }

    public function refreshStats(ApiProvider $provider): void
    {
        $provider->available_imei = $provider->remoteImeiServices()->count();
        $provider->available_server = $provider->remoteServerServices()->count();
        $provider->available_file = $provider->remoteFileServices()->count();

        $provider->used_imei = ImeiService::where('supplier_id', $provider->id)->whereNotNull('remote_id')->count();
        $provider->used_server = ServerService::where('supplier_id', $provider->id)->whereNotNull('remote_id')->count();
        $provider->used_file = FileService::where('supplier_id', $provider->id)->whereNotNull('remote_id')->count();

        $provider->save();
    }
}