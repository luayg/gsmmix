<?php

namespace App\Observers;

use App\Models\ApiProvider;
use App\Services\Providers\LocalServiceManualFallback;

class ProviderDeletionFallbackObserver
{
    public function deleting(ApiProvider $provider): void
    {
        $fallback = app(LocalServiceManualFallback::class);

        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $fallback->convertAllLinked($provider, $kind, 'provider_deleted');
        }
    }
}
