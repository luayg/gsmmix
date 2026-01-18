<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use Illuminate\Support\Facades\Log;

class ProviderManager
{
    /**
     * Sync provider catalog (imei/server/file) + optional balance.
     * Returns: ['ok'=>bool,'balance'=>float|null,'total'=>int,'errors'=>array]
     */
    public function syncProvider(ApiProvider $provider, ?string $onlyType = null, bool $balanceOnly = false): array
    {
        $adapter = ProviderFactory::make($provider);

        $result = [
            'ok'      => false,
            'balance' => null,
            'total'   => 0,
            'errors'  => [],
        ];

        try {
            // 1) balance
            if (method_exists($adapter, 'fetchBalance')) {
                $bal = (float) $adapter->fetchBalance($provider);
                $result['balance'] = $bal;

                // خزّن الرصيد دائمًا إن نجحنا
                $provider->balance = $bal;
            }

            if ($balanceOnly) {
                // لا نحكم على synced من خلال الرصيد
                $provider->save();
                $result['ok'] = true;
                return $result;
            }

            // 2) catalog
            $types = [];

            if ($onlyType) {
                $types = [strtolower($onlyType)];
            } else {
                if ($provider->sync_imei)   $types[] = 'imei';
                if ($provider->sync_server) $types[] = 'server';
                if ($provider->sync_file)   $types[] = 'file';
            }

            $total = 0;
            $hadError = false;

            foreach ($types as $t) {
                if (!$adapter->supportsCatalog($t)) {
                    continue;
                }

                try {
                    $n = (int) $adapter->syncCatalog($provider, $t);
                    $total += $n;
                } catch (\Throwable $e) {
                    $hadError = true;
                    $result['errors'][] = "{$t}: ".$e->getMessage();
                }
            }

            // ✅ synced = true فقط إذا ما في أخطاء + جاب على الأقل خدمة واحدة
            $provider->synced = (!$hadError && $total > 0);
            $provider->save();

            $result['ok'] = !$hadError;
            $result['total'] = $total;

            return $result;

        } catch (\Throwable $e) {
            Log::error('ProviderManager sync failed', [
                'provider_id' => $provider->id,
                'type'        => $provider->type,
                'msg'         => $e->getMessage(),
            ]);

            $provider->synced = false;
            $provider->save();

            $result['errors'][] = $e->getMessage();
            return $result;
        }
    }

    public function syncProviderById(int $providerId, ?string $onlyType = null, bool $balanceOnly = false): array
    {
        $provider = ApiProvider::findOrFail($providerId);
        return $this->syncProvider($provider, $onlyType, $balanceOnly);
    }
}
