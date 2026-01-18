<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
use App\Services\Providers\ProviderAdapterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GsmhubAdapter implements ProviderAdapterInterface
{
    public function type(): string { return 'gsmhub'; }

    public function supportsCatalog(string $serviceType): bool
    {
        return in_array(strtolower($serviceType), ['imei','server','file'], true);
    }

    /**
     * ✅ imei.us يطلب base URL = https://imei.us/public
     * لكن الـAPI الحقيقي عندهم: https://imei.us/api/index.php
     */
    protected function endpoint(ApiProvider $p): string
    {
        $base = rtrim((string)$p->url, '/');

        // لو المستخدم كتب ملف php مباشرة
        if (preg_match('~/[^/]+\.php$~i', $base)) {
            return $base;
        }

        // https://imei.us/public => https://imei.us
        if (preg_match('~/public$~i', $base)) {
            $base = preg_replace('~/public$~i', '', $base);
            $base = rtrim($base, '/');
        }

        return $base . '/api/index.php';
    }

    protected function buildParametersXml(array $params): string
    {
        if (empty($params)) return '';
        $xml = '<PARAMETERS>';
        foreach ($params as $k => $v) {
            $tag = strtoupper((string)$k);
            $val = htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $xml .= "<{$tag}>{$val}</{$tag}>";
        }
        $xml .= '</PARAMETERS>';
        return $xml;
    }

    protected function call(ApiProvider $p, string $action, array $params = []): array
    {
        $url = $this->endpoint($p);

        $payload = [
            'username'      => (string)($p->username ?? ''),
            'apiaccesskey'  => (string)($p->api_key ?? ''),
            'action'        => $action,
            'requestformat' => 'JSON',
            'parameters'    => $this->buildParametersXml($params),
        ];

        $res = Http::asForm()->timeout(120)->post($url, $payload);

        logger()->info('GSMHUB HTTP', [
            'provider_id' => $p->id,
            'url' => $url,
            'action' => $action,
            'http' => $res->status(),
            'content_type' => $res->header('content-type'),
            'body_head' => substr($res->body(), 0, 500),
        ]);

        $json = $res->json();
        if (is_array($json)) return $json;

        return [
            '_http' => $res->status(),
            '_content_type' => $res->header('content-type'),
            '_body' => $res->body(),
        ];
    }

    protected function extractList(array $raw)
    {
        $candidates = [
            data_get($raw, 'SUCCESS.0.LIST'),
            data_get($raw, 'SUCCESS.LIST'),
            data_get($raw, 'LIST'),
            data_get($raw, 'Success.0.List'),
            data_get($raw, 'success.0.list'),
        ];
        foreach ($candidates as $c) {
            if (is_array($c)) return $c;
        }
        return null;
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        $raw = $this->call($provider, 'accountinfo');

        if (isset($raw['_body'])) return 0.0;

        $err = data_get($raw, 'ERROR.0.MESSAGE');
        if ($err) return 0.0;

        $creditraw = data_get($raw, 'SUCCESS.0.AccoutInfo.creditraw');
        if ($creditraw !== null) return (float)$creditraw;

        $credit = data_get($raw, 'SUCCESS.0.AccoutInfo.credit');
        return $credit !== null ? (float)$credit : 0.0;
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

        $err = data_get($raw, 'ERROR.0.MESSAGE');
        if ($err) return 0;

        $list = $this->extractList($raw);
        if (!is_array($list)) return 0;

        // ✅ GSMHub يرسل غالباً Groups => SERVICES
        // LIST: {GroupName: {GROUPNAME:"..", SERVICES:{id:{...}}}}
        // أو أحياناً Map مباشرة {id:{SERVICEID..}}
        $rows = [];

        // حالة Groups
        $first = reset($list);
        $looksGrouped = is_array($first) && (isset($first['SERVICES']) || isset($first['GROUPNAME']));

        if ($looksGrouped) {
            foreach ($list as $group) {
                if (!is_array($group)) continue;
                $groupName = (string)($group['GROUPNAME'] ?? '');
                $services  = $group['SERVICES'] ?? [];
                if (!is_array($services)) continue;

                foreach ($services as $srv) {
                    if (!is_array($srv)) continue;
                    $rows[] = $this->normalizeServiceRow($provider->id, $groupName, $srv);
                }
            }
        } else {
            // Map مباشرة
            foreach ($list as $srv) {
                if (!is_array($srv)) continue;
                $rows[] = $this->normalizeServiceRow($provider->id, (string)($srv['GROUPNAME'] ?? ''), $srv);
            }
        }

        // فلترة null
        $rows = array_values(array_filter($rows));
        if (!$rows) return 0;

        return $this->bulkUpsert($serviceType, $rows);
    }

    protected function normalizeServiceRow(int $apiId, string $groupName, array $srv): ?array
    {
        $remoteId = (string)($srv['SERVICEID'] ?? $srv['id'] ?? '');
        if ($remoteId === '') return null;

        return [
            'api_id'          => $apiId,
            'remote_id'       => $remoteId,
            'name'            => (string)($srv['SERVICENAME'] ?? $srv['name'] ?? ''),
            'group_name'      => $groupName,
            'price'           => (float)($srv['CREDIT'] ?? $srv['credit'] ?? 0),
            'time'            => (string)($srv['TIME'] ?? $srv['time'] ?? ''),
            'info'            => (string)($srv['INFO'] ?? $srv['info'] ?? ''),
            'additional_data' => json_encode($srv, JSON_UNESCAPED_UNICODE),
            'updated_at'      => now(),
            'created_at'      => now(),
        ];
    }

    protected function bulkUpsert(string $serviceType, array $rows): int
    {
        // ✅ upsert سريع جداً ويحل مشكلة timeout/تعليق ScienceNow
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
