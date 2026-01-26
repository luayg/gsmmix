<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use App\Services\Providers\Adapters\DhruAdapter;
use App\Services\Providers\Adapters\GsmhubAdapter;
use App\Services\Providers\Adapters\SimpleLinkAdapter;
use App\Services\Providers\Adapters\UnlockbaseAdapter;
use App\Services\Providers\Adapters\WebxAdapter;
use InvalidArgumentException;

class ProviderFactory
{
    public function make(ApiProvider $provider): ProviderAdapterInterface
    {
        return match ($provider->type) {
            'dhru' => app(DhruAdapter::class),
            'gsmhub' => app(GsmhubAdapter::class),
            'simple_link' => app(SimpleLinkAdapter::class),
            'webx' => app(WebxAdapter::class),
            'unlockbase' => app(UnlockbaseAdapter::class),
            default => throw new InvalidArgumentException("Unsupported provider type: {$provider->type}"),
        };
    }
}
