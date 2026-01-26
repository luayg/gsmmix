<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderAdapterInterface;

class UnlockbaseAdapter implements ProviderAdapterInterface
{
    public function type(): string
    {
        return 'unlockbase';
    }

    public function supportsCatalog(string $kind): bool
    {
        return false;
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        return 0.0;
    }

    public function syncCatalog(ApiProvider $provider, string $kind): int
    {
        return 0;
    }
}
