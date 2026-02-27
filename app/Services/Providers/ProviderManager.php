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
            $result['errors'][] = 'Provider is inactive';
            return $result;
        }

        $adapter = $this->factory->make($provider);

        // 1) Balance first — and ALWAYS try to save it
        try {
            $balance = (float) $adapter->fetchBalance($provider);
            $provider->balance = $balance;
            $provider->save();
            $result['balance'] = $balance;
        } catch (\Throwable $e) {
            $short = $this->shortProviderError($e);
            $result['errors'][] = 'Balance: ' . $short;

            // keep detailed error in logs only
            Log::warning('Fetch balance failed', [
                'provider_id' => $provider->id,
                'type' => $provider->type,
                'error_class' => get_class($e),
                'error' => $e->getMessage(),
            ]);
        }

        if ($balanceOnly) {
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
            if ($provider->sync_imei)  $kinds[] = 'imei';
            if ($provider->sync_server) $kinds[] = 'server';
            if ($provider->sync_file)  $kinds[] = 'file';
        }

        $syncedAny = false;

        foreach ($kinds as $kind) {
            try {
                $count = $adapter->syncCatalog($provider, $kind);
                $result['catalog'][$kind] = ['ok' => true, 'count' => (int)$count];
                $syncedAny = true;
            } catch (\Throwable $e) {
                $short = $this->shortProviderError($e);

                // FILE not active: warning only
                if ($kind === 'file' && $this->isNoFileServiceActive($short)) {
                    $result['warnings'][] = 'No File Service Active (skipped).';
                    $result['catalog'][$kind] = ['ok' => false, 'count' => 0, 'note' => 'skipped'];
                    $provider->sync_file = 0;
                    $provider->save();
                    continue;
                }

                $result['errors'][] = strtoupper($kind) . ': ' . $short;
                $result['catalog'][$kind] = ['ok' => false, 'count' => 0, 'error' => $short];

                // detailed error in logs only
                Log::error('Catalog sync failed', [
                    'provider_id' => $provider->id,
                    'type' => $provider->type,
                    'kind' => $kind,
                    'error_class' => get_class($e),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 3) synced = true if any catalog succeeded
        $provider->synced = $syncedAny ? 1 : 0;
        $provider->save();

        $this->refreshStats($provider);

        $result['synced'] = (bool) $provider->synced;
        $result['balance'] = (float) $provider->balance;

        return $result;
    }

    private function shortProviderError(\Throwable $e): string
    {
        $msg = trim((string) $e->getMessage());

        // If our WebxClient already produced the short messages, keep them.
        if ($msg !== '' && (
            str_contains($msg, 'IP BLOCKED')
            || str_contains($msg, 'ACCESS DENIED')
            || str_contains($msg, 'Provider unavailable')
            || str_contains($msg, 'Connection failed')
            || str_contains($msg, 'RATE LIMITED')
        )) {
            return $msg;
        }

        // Generic compression for ANY adapter that throws Laravel-like message:
        // "HTTP request returned status code 503: <!DOCTYPE html>..."
        $m = strtolower($msg);

        if (str_contains($m, 'status code 503') || str_contains($m, 'http 503')) {
            return 'IP BLOCKED (HTTP 503). Provider رفض اتصال السيرفر. اعمل Reset/Whitelist للـ IP.';
        }

        if (str_contains($m, 'status code 403') || str_contains($m, 'http 403') || str_contains($m, 'forbidden')) {
            return 'ACCESS DENIED (HTTP 403). Provider رفض الطلب (ممكن IP غير مسموح).';
        }

        if (str_contains($m, 'status code 401') || str_contains($m, 'http 401') || str_contains($m, 'unauthorized')) {
            return 'ACCESS DENIED (HTTP 401). تحقق من بيانات الدخول/IP.';
        }

        if (str_contains($m, 'connection refused') || str_contains($m, 'could not resolve') || str_contains($m, 'timeout')) {
            return 'Connection failed. Provider may be blocking your server IP.';
        }

        // fallback: keep it short (max 160 chars)
        if ($msg === '') return 'Provider error.';
        if (mb_strlen($msg) > 160) return mb_substr($msg, 0, 160) . '...';
        return $msg;
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