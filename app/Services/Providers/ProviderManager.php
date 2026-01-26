<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use App\Models\FileService;
use App\Models\ImeiService;
use App\Models\ServerService;
use Illuminate\Support\Facades\Log;

class ProviderManager
{
    private ProviderFactory $factory;

    public function __construct(ProviderFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Sync provider:
     * - fetch balance
     * - sync catalogs based on flags
     * - refresh available/used counters
     *
     * @param string|null $onlyKind imei|server|file
     * @param bool $balanceOnly if true: only balance update
     * @return array result payload
     */
    public function sync(ApiProvider $provider, ?string $onlyKind = null, bool $balanceOnly = false): array
    {
        $result = [
            'provider_id' => $provider->id,
            'type' => $provider->type,
            'balance' => null,
            'synced' => false,
            'catalog' => [],
            'errors' => [],
        ];

        if (!$provider->active) {
            $result['errors'][] = 'Provider is inactive';
            return $result;
        }

        $adapter = $this->factory->make($provider);

        try {
            $balance = $adapter->fetchBalance($provider);
            $provider->balance = $balance;
            $provider->save();

            $result['balance'] = $balance;

            if ($balanceOnly) {
                $provider->synced = true;
                $provider->save();
                $result['synced'] = true;

                $this->refreshStats($provider);
                return $result;
            }

            if (!$provider->ignore_low_balance && $balance <= 0) {
                $provider->synced = false;
                $provider->save();
                $result['errors'][] = 'Low balance (sync stopped because ignore_low_balance = 0)';
                $this->refreshStats($provider);
                return $result;
            }

            $kinds = [];

            if ($onlyKind) {
                $kinds[] = $onlyKind;
            } else {
                if ($provider->sync_imei) $kinds[] = 'imei';
                if ($provider->sync_server) $kinds[] = 'server';
                if ($provider->sync_file) $kinds[] = 'file';
            }

            $syncedAny = false;

            foreach ($kinds as $kind) {
                if (!$adapter->supportsCatalog($kind)) {
                    $result['catalog'][$kind] = ['supported' => false, 'count' => 0];
                    continue;
                }

                $count = $adapter->syncCatalog($provider, $kind);
                $result['catalog'][$kind] = ['supported' => true, 'count' => $count];

                $syncedAny = true;
            }

            $provider->synced = $syncedAny ? 1 : 0;
            $provider->save();

            $this->refreshStats($provider);

            $result['synced'] = (bool) $provider->synced;
        } catch (\Throwable $e) {
            Log::error('Provider sync failed', [
                'provider_id' => $provider->id,
                'type' => $provider->type,
                'error' => $e->getMessage(),
            ]);

            $provider->synced = 0;
            $provider->save();

            $this->refreshStats($provider);

            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Refresh provider available/used counters from DB.
     */
    public function refreshStats(ApiProvider $provider): void
    {
        // available = remote count
        $provider->available_imei = $provider->remoteImeiServices()->count();
        $provider->available_server = $provider->remoteServerServices()->count();
        $provider->available_file = $provider->remoteFileServices()->count();

        // used = local services linked to this provider (supplier_id is still used in your schema)
        $provider->used_imei = ImeiService::where('supplier_id', $provider->id)->whereNotNull('remote_id')->count();
        $provider->used_server = ServerService::where('supplier_id', $provider->id)->whereNotNull('remote_id')->count();
        $provider->used_file = FileService::where('supplier_id', $provider->id)->whereNotNull('remote_id')->count();

        $provider->save();
    }
}
