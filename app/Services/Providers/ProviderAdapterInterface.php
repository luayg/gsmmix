<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;

interface ProviderAdapterInterface
{
    public function type(): string;

    /**
     * kind: imei | server | file
     */
    public function supportsCatalog(string $kind): bool;

    public function fetchBalance(ApiProvider $provider): float;

    /**
     * Return number of synced items (created/updated)
     */
    public function syncCatalog(ApiProvider $provider, string $kind): int;
}
