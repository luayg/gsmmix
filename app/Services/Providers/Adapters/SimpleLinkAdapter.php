<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderAdapterInterface;

class SimpleLinkAdapter implements ProviderAdapterInterface
{
    public function type(): string { return 'simple_link'; }

    public function supportsCatalog(string $serviceType): bool
    {
        // عادة لا يوجد list خدمات
        return false;
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        // لا يوجد balance عادة
        return 0.0;
    }

    public function syncCatalog(ApiProvider $provider, string $serviceType): int
    {
        // لا يوجد sync
        return 0;
    }

    /** helper: إعدادات simple_link من params */
    public function method(ApiProvider $p): string
    {
        return strtolower((string)($p->params_json['method'] ?? 'post')); // post|get
    }

    public function mainField(ApiProvider $p): string
    {
        return (string)($p->params_json['main_field'] ?? 'imei');
    }
}
