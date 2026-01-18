<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Services\Providers\ProviderAdapterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DhruAdapter implements ProviderAdapterInterface
{
    public function type(): string { return 'dhru'; }

    public function supportsCatalog(string $serviceType): bool
    {
        return in_array(strtolower($serviceType), ['imei','server','file'], true);
    }

    protected function endpoint(ApiProvider $p): string
    {
        $base = rtrim((string)$p->url, '/');
        return $base; // DHRU endpoint يكون جاهز عادة (api url)
    }

    protected function call(ApiProvider $p, string $action, array $params = []): array
    {
        $url = $this->endpoint($p);

        $payload = array_merge([
            'username' => (string)($p->username ?? ''),
            'api_key'  => (string)($p->api_key ?? ''),
            'action'   => $action,
        ], $params);

        $res = Http::asForm()->timeout(120)->post($url, $payload);

        $json = $res->json();
        if (is_array($json)) return $json;

        return [
            '_http' => $res->status(),
            '_content_type' => $res->header('content-type'),
            '_body' => $res->body(),
        ];
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        $raw = $this->call($provider, 'accountinfo');

        if (isset($raw['_body'])) return 0.0;

        $bal = data_get($raw, 'SUCCESS.0.balance');
        return $bal !== null ? (float)$bal : 0.0;
    }

    public function syncCatalog(ApiProvider $provider, string $serviceType): int
    {
        $serviceType = strtolower($serviceType);

        $action = match ($serviceType) {
            'imei'   => 'imeiservicelist',
            'server' => 'serverservicelist',
            'file'   => 'fileservicelist',
            default  => null,
        };
        if (!$action) return 0;

        $raw = $this->call($provider, $action);

        if (isset($raw['_body'])) return 0;

        $list = data_get($raw, 'SUCCESS.0.LIST');
        if (!is_array($list)) return 0;

        $rows = [];
        foreach ($list as $srv) {
            if (!is_array($srv)) continue;

            $remoteId = (string)($srv['SERVICEID'] ?? $srv['id'] ?? '');
            if ($remoteId === '') continue;

            $rows[] = [
                'api_id'          => $provider->id,
                'remote_id'       => $remoteId,
                'name'            => (string)($srv['SERVICENAME'] ?? $srv['name'] ?? ''),
                'group_name'      => (string)($srv['GROUPNAME'] ?? $srv['group'] ?? ''),
                'price'           => (float)($srv['CREDIT'] ?? $srv['credit'] ?? 0),
                'time'            => (string)($srv['TIME'] ?? $srv['time'] ?? ''),
                'info'            => (string)($srv['INFO'] ?? $srv['info'] ?? ''),
                'additional_data' => json_encode($srv, JSON_UNESCAPED_UNICODE),
                'updated_at'      => now(),
                'created_at'      => now(),
            ];
        }

        $rows = array_values(array_filter($rows));
        if (!$rows) return 0;

        $uniqueBy = ['api_id', 'remote_id'];
        $update = ['name','group_name','price','time','info','additional_data','updated_at'];

        if ($serviceType === 'imei') {
            DB::table((new RemoteImeiService)->getTable())->upsert($rows, $uniqueBy, $update);
        } elseif ($serviceType === 'server') {
            DB::table((new RemoteServerService)->getTable())->upsert($rows, $uniqueBy, $update);
        } else {
            DB::table((new RemoteFileService)->getTable())->upsert($rows, $uniqueBy, $update);
        }

        return count($rows);
    }
}
