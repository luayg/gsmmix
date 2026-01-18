<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderAdapterInterface;

class UnlockbaseAdapter implements ProviderAdapterInterface
{
    public function type(): string { return 'unlockbase'; }

    public function supportsCatalog(string $serviceType): bool
    {
        // غالبًا IMEI فقط
        return $serviceType === 'imei';
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        // TODO: اربط API unlockbase هنا (حسب البروتوكول لديك)
        return 0.0;
    }

    public function syncCatalog(ApiProvider $provider, string $serviceType): int
    {
        if (strtolower($serviceType) !== 'imei') return 0;

        // TODO: جلب خدمات IMEI و upsert إلى remote_imei_services
        return 0;
    }
}
