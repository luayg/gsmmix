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

        // Dedup flags
        $ipBlockedSeen = false;

        // 1) Balance first — and ALWAYS try to save it
        try {
            $balance = (float) $adapter->fetchBalance($provider);
            $provider->balance = $balance;
            $provider->save();
            $result['balance'] = $balance;
        } catch (\Throwable $e) {
            $short = $this->shortProviderError($e);

            // Deduplicate IP blocked across balance + kinds
            if ($short === 'IP BLOCKED - Reset Provider IP') {
                if (!$ipBlockedSeen) {
                    $result['errors'][] = $short;
                    $ipBlockedSeen = true;
                }
            } else {
                $this->addUniqueError($result['errors'], 'Balance: ' . $short);
            }

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
            if ($provider->sync_imei)   $kinds[] = 'imei';
            if ($provider->sync_server) $kinds[] = 'server';
            if ($provider->sync_file)   $kinds[] = 'file';
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

                // Always store per-kind catalog status (for UI if you use it)
                $result['catalog'][$kind] = ['ok' => false, 'count' => 0, 'error' => $short];

                // Deduplicate IP blocked across balance + kinds
                if ($short === 'IP BLOCKED - Reset Provider IP') {
                    if (!$ipBlockedSeen) {
                        $result['errors'][] = $short;
                        $ipBlockedSeen = true;
                    }
                } else {
                    // Keep short and unique; don't spam many lines
                    $this->addUniqueError($result['errors'], $short);
                }

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

    /**
     * Add at most 2 unique errors (besides IP blocked).
     */
    private function addUniqueError(array &$errors, string $msg): void
    {
        $msg = trim($msg);
        if ($msg === '') return;

        // Do not duplicate
        if (in_array($msg, $errors, true)) return;

        // If IP blocked already present, don't add extra noise unless necessary.
        if (in_array('IP BLOCKED - Reset Provider IP', $errors, true)) {
            return;
        }

        // Limit noise: keep only first 2 messages
        if (count($errors) >= 2) return;

        $errors[] = $msg;
    }

    private function shortProviderError(\Throwable $e): string
    {
        $msg = trim((string) $e->getMessage());
        $m = strtolower($msg);

        if ($msg === 'IP BLOCKED - Reset Provider IP') {
            return $msg;
        }

        // Treat typical block/unavailable as IP blocked (short)
        if (
            str_contains($m, 'status code 503') ||
            str_contains($m, 'http 503') ||
            str_contains($m, 'service unavailable') ||
            str_contains($m, '<!doctype html') ||
            str_contains($m, '<html') ||
            str_contains($m, 'cloudflare') ||
            str_contains($m, 'forbidden') ||
            str_contains($m, 'unauthorized') ||
            str_contains($m, 'connection refused') ||
            str_contains($m, 'could not resolve') ||
            str_contains($m, 'timed out') ||
            str_contains($m, 'timeout')
        ) {
            return 'IP BLOCKED - Reset Provider IP';
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