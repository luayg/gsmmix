<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
use App\Services\Providers\ProviderAdapterInterface;
use App\Services\Api\DhruClient;
use Illuminate\Support\Carbon;

class DhruAdapter implements ProviderAdapterInterface
{
    public function type(): string { return 'dhru'; }

    protected function client(ApiProvider $p): DhruClient
    {
        return new DhruClient($p->url, (string)$p->username, (string)$p->api_key);
    }

    public function supportsCatalog(string $serviceType): bool
    {
        $serviceType = strtolower($serviceType);
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
            $items = $c->imeiServices();
            return $this->upsertImei($provider->id, $items);
        }

        if ($serviceType === 'server') {
            $items = $c->serverServices();
            return $this->upsertServer($provider->id, $items);
        }

        if ($serviceType === 'file') {
            $items = $c->fileServices();
            return $this->upsertFile($provider->id, $items);
        }

        return 0;
    }

    protected function upsertImei(int $apiId, array $items): int
    {
        $rows = [];
        $now = Carbon::now();

        foreach ($items as $srv) {
            $remoteId = (string)($srv['SERVICEID'] ?? '');
            if ($remoteId === '') continue;

            $rows[] = [
                'api_id'            => $apiId,
                'remote_id'         => $remoteId,
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
                'updated_at'        => $now,
                'created_at'        => $now,
            ];
        }

        if (!$rows) return 0;

        $updateCols = [
            'name','group_name','price','time','info',
            'min_qty','max_qty','credit_groups','additional_fields','additional_data','params',
            'updated_at'
        ];

        foreach (array_chunk($rows, 500) as $chunk) {
            RemoteImeiService::query()->upsert($chunk, ['api_id','remote_id'], $updateCols);
        }

        return count($rows);
    }

    protected function upsertServer(int $apiId, array $items): int
    {
        $rows = [];
        $now = Carbon::now();

        foreach ($items as $srv) {
            $remoteId = (string)($srv['SERVICEID'] ?? '');
            if ($remoteId === '') continue;

            $rows[] = [
                'api_id'            => $apiId,
                'remote_id'         => $remoteId,
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
                'updated_at'        => $now,
                'created_at'        => $now,
            ];
        }

        if (!$rows) return 0;

        $updateCols = [
            'name','group_name','price','time','info',
            'min_qty','max_qty','credit_groups','additional_fields','additional_data','params',
            'updated_at'
        ];

        foreach (array_chunk($rows, 500) as $chunk) {
            RemoteServerService::query()->upsert($chunk, ['api_id','remote_id'], $updateCols);
        }

        return count($rows);
    }

    protected function upsertFile(int $apiId, array $items): int
    {
        $rows = [];
        $now = Carbon::now();

        foreach ($items as $srv) {
            $remoteId = (string)($srv['SERVICEID'] ?? '');
            if ($remoteId === '') continue;

            $rows[] = [
                'api_id'             => $apiId,
                'remote_id'          => $remoteId,
                'name'               => (string)($srv['SERVICENAME'] ?? ''),
                'group_name'         => (string)($srv['group'] ?? ''),
                'price'              => (float)($srv['CREDIT'] ?? 0),
                'time'               => (string)($srv['TIME'] ?? ''),
                'info'               => (string)($srv['INFO'] ?? ''),
                'allowed_extensions' => (string)($srv['ALLOW_EXTENSION'] ?? ''),
                'additional_fields'  => isset($srv['ADDFIELDS']) ? json_encode($srv['ADDFIELDS'], JSON_UNESCAPED_UNICODE) : null,
                'additional_data'    => isset($srv['ADDDATA']) ? json_encode($srv['ADDDATA'], JSON_UNESCAPED_UNICODE) : null,
                'params'             => isset($srv['PARAMS']) ? json_encode($srv['PARAMS'], JSON_UNESCAPED_UNICODE) : null,
                'updated_at'         => $now,
                'created_at'         => $now,
            ];
        }

        if (!$rows) return 0;

        $updateCols = [
            'name','group_name','price','time','info','allowed_extensions',
            'additional_fields','additional_data','params',
            'updated_at'
        ];

        foreach (array_chunk($rows, 500) as $chunk) {
            RemoteFileService::query()->upsert($chunk, ['api_id','remote_id'], $updateCols);
        }

        return count($rows);
    }
}
