<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderAdapterInterface;

class SimpleLinkAdapter implements ProviderAdapterInterface
{
    public function type(): string
    {
        return 'simple_link';
    }

    public function supportsCatalog(string $kind): bool
    {
        return false;
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        // Simple link usually has no balance API
        return 0.0;
    }

    public function syncCatalog(ApiProvider $provider, string $kind): int
    {
        return 0;
    }
}
