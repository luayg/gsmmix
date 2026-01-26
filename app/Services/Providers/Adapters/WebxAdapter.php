<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
use App\Services\Providers\ProviderAdapterInterface;

class WebxAdapter implements ProviderAdapterInterface
{
    public function type(): string { return 'webx'; }

    public function supportsCatalog(string $serviceType): bool
    {
        return in_array($serviceType, ['imei','server','file'], true);
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        // TODO: اربط WebxClient هنا
        // مثال:
        // $client = new \App\Services\Api\Webx\WebxClient($provider->url, $provider->username, $provider->api_key);
        // $info = $client->getAccountInfo();
        // return (float)($info['balance'] ?? 0);

        return 0.0;
    }

    public function syncCatalog(ApiProvider $provider, string $serviceType): int
    {
        $serviceType = strtolower($serviceType);

        // TODO: اربط WebxClient list services هنا
        // يجب أن ترجع array من الخدمات ثم map إلى remote_* مثل DHRU
        $services = []; // placeholder

        $count = 0;
        foreach ($services as $s) {
            // افترض $s يحتوي id,name,price,time,info,group
            $remoteId = (string)($s['id'] ?? '');
            if ($remoteId === '') continue;

            if ($serviceType === 'imei') {
                RemoteImeiService::updateOrCreate(
                    ['api_provider_id'=>$provider->id,'remote_id'=>$remoteId],
                    [
                        'name'=>$s['name'] ?? '',
                        'group_name'=>$s['group'] ?? '',
                        'price'=>(float)($s['price'] ?? 0),
                        'time'=>$s['time'] ?? '',
                        'info'=>$s['info'] ?? '',
                        'additional_data'=>json_encode($s, JSON_UNESCAPED_UNICODE),
                    ]
                );
            } elseif ($serviceType === 'server') {
                RemoteServerService::updateOrCreate(
                    ['api_provider_id'=>$provider->id,'remote_id'=>$remoteId],
                    [
                        'name'=>$s['name'] ?? '',
                        'group_name'=>$s['group'] ?? '',
                        'price'=>(float)($s['price'] ?? 0),
                        'time'=>$s['time'] ?? '',
                        'info'=>$s['info'] ?? '',
                        'additional_data'=>json_encode($s, JSON_UNESCAPED_UNICODE),
                    ]
                );
            } else {
                RemoteFileService::updateOrCreate(
                    ['api_provider_id'=>$provider->id,'remote_id'=>$remoteId],
                    [
                        'name'=>$s['name'] ?? '',
                        'group_name'=>$s['group'] ?? '',
                        'price'=>(float)($s['price'] ?? 0),
                        'time'=>$s['time'] ?? '',
                        'info'=>$s['info'] ?? '',
                        'additional_data'=>json_encode($s, JSON_UNESCAPED_UNICODE),
                    ]
                );
            }

            $count++;
        }

        return $count;
    }
}
