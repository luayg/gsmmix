<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Services\Providers\ProviderAdapterInterface;

class SimpleLinkAdapter implements ProviderAdapterInterface
{
    public function type(): string
    {
        return 'simple_link';
    }

    public function supportsCatalog(string $kind): bool
    {
        return in_array($kind, ['imei', 'server', 'file'], true);
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        // Simple Link is not a real API provider with remote balance support.
        return 0.0;
    }

    public function syncCatalog(ApiProvider $provider, string $kind): int
    {
        if (!in_array($kind, ['imei', 'server', 'file'], true)) {
            return 0;
        }

        [$modelClass] = match ($kind) {
            'imei'   => [RemoteImeiService::class],
            'server' => [RemoteServerService::class],
            'file'   => [RemoteFileService::class],
            default  => [RemoteImeiService::class],
        };

        // Simple Link = one standard virtual remote service only.
        // No outbound HTTP call, no external sync endpoint required.
        $modelClass::query()->where('api_provider_id', $provider->id)->delete();

        $insert = [
            'api_provider_id'   => $provider->id,
            'group_name'        => 'Simple Link',
            'remote_id'         => '1',
            'name'              => 'Link service',
            'price'             => 0,
            'time'              => '',
            'additional_fields' => [],
        ];

        if ($kind === 'file') {
            $insert['allowed_extensions'] = '';
        }

        $modelClass::query()->create($insert);

        return 1;
    }
}