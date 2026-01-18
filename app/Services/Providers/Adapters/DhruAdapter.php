<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
use App\Services\Providers\ProviderAdapterInterface;
use App\Services\Api\DhruClient; // ✅ الصحيح (بدون Dhru\)

class DhruAdapter implements ProviderAdapterInterface
{
    public function type(): string { return 'dhru'; }

    protected function client(ApiProvider $p): DhruClient
    {
        return new DhruClient($p->url, (string)$p->username, (string)$p->api_key);
    }

    public function supportsCatalog(string $serviceType): bool
    {
        return in_array($serviceType, ['imei','server','file'], true);
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        $acc = $this->client($provider)->accountInfo();
        return (float)($acc['credits'] ?? 0);
    }

    public function syncCatalog(ApiProvider $provider, string $serviceType): int
    {
        $serviceType = strtolower($serviceType);
        $c = $this->client($provider);

        if ($serviceType === 'imei') {
            $items = $c->imeiServices(); // ✅ موجودة في DhruClient عندك :contentReference[oaicite:3]{index=3}
            return $this->upsertImei($provider->id, $items);
        }

        if ($serviceType === 'server') {
            $items = $c->serverServices(); // ✅ :contentReference[oaicite:4]{index=4}
            return $this->upsertServer($provider->id, $items);
        }

        if ($serviceType === 'file') {
            $items = $c->fileServices(); // ✅ :contentReference[oaicite:5]{index=5}
            return $this->upsertFile($provider->id, $items);
        }

        return 0;
    }

    protected function upsertImei(int $apiId, array $items): int
    {
        $count = 0;
        foreach ($items as $srv) {
            $remoteId = (string)($srv['SERVICEID'] ?? '');
            if ($remoteId === '') continue;

            RemoteImeiService::updateOrCreate(
                ['api_id' => $apiId, 'remote_id' => $remoteId],
                [
                    'name'              => (string)($srv['SERVICENAME'] ?? ''),
                    'group_name'        => (string)($srv['group'] ?? ''),
                    'price'             => (float)($srv['CREDIT'] ?? 0),
                    'time'              => (string)($srv['TIME'] ?? ''),
                    'info'              => (string)($srv['INFO'] ?? ''),
                    'min_qty'           => isset($srv['MINQNT']) ? (string)$srv['MINQNT'] : null,
                    'max_qty'           => isset($srv['MAXQNT']) ? (string)$srv['MAXQNT'] : null,
                    'credit_groups'     => isset($srv['CREDITGROUPS']) ? json_encode($srv['CREDITGROUPS'], JSON_UNESCAPED_UNICODE) : null,
                    'additional_fields' => isset($srv['ADDFIELDS']) ? json_encode($srv['ADDFIELDS'], JSON_UNESCAPED_UNICODE) : null,
                    'additional_data'   => isset($srv['ADDDATA']) ? json_encode($srv['ADDDATA'], JSON_UNESCAPED_UNICODE) : null,
                    'params'            => isset($srv['PARAMS']) ? json_encode($srv['PARAMS'], JSON_UNESCAPED_UNICODE) : null,
                ]
            );
            $count++;
        }
        return $count;
    }

    protected function upsertServer(int $apiId, array $items): int
    {
        $count = 0;
        foreach ($items as $srv) {
            $remoteId = (string)($srv['SERVICEID'] ?? '');
            if ($remoteId === '') continue;

            RemoteServerService::updateOrCreate(
                ['api_id' => $apiId, 'remote_id' => $remoteId],
                [
                    'name'              => (string)($srv['SERVICENAME'] ?? ''),
                    'group_name'        => (string)($srv['group'] ?? ''),
                    'price'             => (float)($srv['CREDIT'] ?? 0),
                    'time'              => (string)($srv['TIME'] ?? ''),
                    'info'              => (string)($srv['INFO'] ?? ''),
                    'min_qty'           => isset($srv['MINQNT']) ? (string)$srv['MINQNT'] : null,
                    'max_qty'           => isset($srv['MAXQNT']) ? (string)$srv['MAXQNT'] : null,
                    'credit_groups'     => isset($srv['CREDITGROUPS']) ? json_encode($srv['CREDITGROUPS'], JSON_UNESCAPED_UNICODE) : null,
                    'additional_fields' => isset($srv['ADDFIELDS']) ? json_encode($srv['ADDFIELDS'], JSON_UNESCAPED_UNICODE) : null,
                    'additional_data'   => isset($srv['ADDDATA']) ? json_encode($srv['ADDDATA'], JSON_UNESCAPED_UNICODE) : null,
                    'params'            => isset($srv['PARAMS']) ? json_encode($srv['PARAMS'], JSON_UNESCAPED_UNICODE) : null,
                ]
            );
            $count++;
        }
        return $count;
    }

    protected function upsertFile(int $apiId, array $items): int
    {
        $count = 0;
        foreach ($items as $srv) {
            $remoteId = (string)($srv['SERVICEID'] ?? '');
            if ($remoteId === '') continue;

            RemoteFileService::updateOrCreate(
                ['api_id' => $apiId, 'remote_id' => $remoteId],
                [
                    'name'               => (string)($srv['SERVICENAME'] ?? ''),
                    'group_name'         => (string)($srv['group'] ?? ''),
                    'price'              => (float)($srv['CREDIT'] ?? 0),
                    'time'               => (string)($srv['TIME'] ?? ''),
                    'info'               => (string)($srv['INFO'] ?? ''),
                    'allowed_extensions' => (string)($srv['ALLOW_EXTENSION'] ?? ''),
                    'additional_fields'  => isset($srv['ADDFIELDS']) ? json_encode($srv['ADDFIELDS'], JSON_UNESCAPED_UNICODE) : null,
                    'additional_data'    => isset($srv['ADDDATA']) ? json_encode($srv['ADDDATA'], JSON_UNESCAPED_UNICODE) : null,
                    'params'             => isset($srv['PARAMS']) ? json_encode($srv['PARAMS'], JSON_UNESCAPED_UNICODE) : null,
                ]
            );
            $count++;
        }
        return $count;
    }
}
