<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
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
        // عندك غالباً /api/index.php أو dhru api endpoint
        return rtrim((string)$p->url, '/');
    }

    protected function call(ApiProvider $p, string $action, array $params = []): array
    {
        $url = $this->endpoint($p);

        $payload = array_merge([
            'username' => (string)($p->username ?? ''),
            'apiaccesskey' => (string)($p->api_key ?? ''),
            'action' => $action,
            'requestformat' => 'JSON',
        ], $params);

        $res = Http::asForm()->timeout(120)->post($url, $payload);
        $json = $res->json();
        return is_array($json) ? $json : [];
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        $raw = $this->call($provider, 'accountinfo');

        $credit = data_get($raw, 'SUCCESS.0.ACCOUNTINFO.credit')
               ?? data_get($raw, 'SUCCESS.0.AccoutInfo.credit')
               ?? data_get($raw, 'SUCCESS.0.credit');

        return $credit !== null ? (float)$credit : 0.0;
    }

    public function syncCatalog(ApiProvider $provider, string $serviceType): int
    {
        $serviceType = strtolower($serviceType);

        // ✅ عدّل الـactions حسب DHRU عندك (إن كانت مختلفة)
        $action = match ($serviceType) {
            'imei'   => 'getimeiservicelist',
            'server' => 'getserverservicelist',
            'file'   => 'getfileservicelist',
            default  => null,
        };
        if (!$action) return 0;

        $raw = $this->call($provider, $action);

        $list = data_get($raw, 'SUCCESS.0.LIST')
             ?? data_get($raw, 'SUCCESS.0.list')
             ?? data_get($raw, 'LIST')
             ?? [];

        if (!is_array($list) || empty($list)) return 0;

        $rows = [];
        foreach ($list as $srv) {
            if (!is_array($srv)) continue;

            $remoteId = (string)($srv['SERVICEID'] ?? $srv['service_id'] ?? $srv['id'] ?? '');
            if ($remoteId === '') continue;

            $rows[] = [
                'api_id'          => $provider->id,
                'remote_id'       => $remoteId,
                'name'            => (string)($srv['SERVICENAME'] ?? $srv['name'] ?? ''),
                'group_name'      => (string)($srv['GROUPNAME'] ?? $srv['group'] ?? ''),
                'price'           => (float)($srv['CREDIT'] ?? $srv['price'] ?? 0),
                'time'            => (string)($srv['TIME'] ?? ''),
                'info'            => (string)($srv['INFO'] ?? ''),
                'additional_data' => json_encode($srv, JSON_UNESCAPED_UNICODE),
                'updated_at'      => now(),
                'created_at'      => now(),
            ];
        }

        if (!$rows) return 0;

        $uniqueBy = ['api_id','remote_id'];
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
