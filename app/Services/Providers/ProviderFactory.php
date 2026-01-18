<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use App\Services\Providers\Adapters\DhruAdapter;
use App\Services\Providers\Adapters\GsmhubAdapter;
use App\Services\Providers\Adapters\WebxAdapter;
use App\Services\Providers\Adapters\UnlockbaseAdapter;
use App\Services\Providers\Adapters\SimpleLinkAdapter;
use InvalidArgumentException;

class ProviderFactory
{
    public static function make(ApiProvider $provider): ProviderAdapterInterface
    {
        return match ($provider->type) {
            'dhru'        => app(DhruAdapter::class),
            'gsmhub'      => app(GsmhubAdapter::class),
            'webx'        => app(WebxAdapter::class),
            'unlockbase'  => app(UnlockbaseAdapter::class),
            'simple_link' => app(SimpleLinkAdapter::class),
            default       => throw new InvalidArgumentException("Unknown provider type: {$provider->type}"),
        };
    }
}
