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
    public function type(): string
    {
        return 'gsmhub';
    }

    public function supportsCatalog(string $serviceType): bool
    {
        return in_array(strtolower($serviceType), ['imei', 'server', 'file'], true);
    }

    /**
     * imei.us يطلب base URL = https://imei.us/public
     * لكن endpoint الحقيقي يكون: https://imei.us/api/index.php
     */
    protected function endpoint(ApiProvider $p): string
    {
        $base = rtrim((string)$p->url, '/');

        // إذا المستخدم كتب ملف php مباشرة
        if (preg_match('~/[^/]+\.php$~i', $base)) {
            return $base;
        }

        // إذا انتهى بـ /public -> احذف public
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

        $res = Http::asForm()
            ->timeout(90)
            ->post($url, $payload);

        logger()->info('GSMHUB HTTP', [
            'provider_id'   => $p->id,
            'url'           => $url,
            'action'        => $action,
            'http'          => $res->status(),
            'content_type'  => $res->header('content-type'),
            'body_head'     => substr($res->body(), 0, 500),
        ]);

        $json = $res->json();
        if (is_array($json)) return $json;

        return [
            '_http'         => $res->status(),
            '_content_type' => $res->header('content-type'),
            '_body'         => $res->body(),
        ];
    }

    protected function extractList(array $raw): ?array
    {
        $candidates = [
            data_get($raw, 'SUCCESS.0.LIST'),
            data_get($raw, 'SUCCESS.LIST'),
            data_get($raw, 'LIST'),
        ];

        foreach ($candidates as $c) {
            if (is_array($c)) return $c;
        }

        return null;
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        $raw = $this->call($provider, 'accountinfo');

        logger()->info('GSMHUB RAW', [
            'provider_id' => $provider->id,
            'action'      => 'accountinfo',
            'raw_keys'    => is_array($raw) ? array_keys($raw) : gettype($raw),
        ]);

        if (isset($raw['_body'])) return 0.0;

        $err = data_get($raw, 'ERROR.0.MESSAGE');
        if ($err) {
            logger()->error('GSMHUB accountinfo ERROR', [
                'provider_id' => $provider->id,
                'error'       => $err,
            ]);
            return 0.0;
        }

        // imei.us يرجع credit (وليس creditraw)
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

        logger()->info('GSMHUB RAW', [
            'provider_id' => $provider->id,
            'action'      => $action,
            'raw_keys'    => is_array($raw) ? array_keys($raw) : gettype($raw),
        ]);

        if (isset($raw['_body'])) {
            logger()->error("GSMHUB {$action} NON_JSON_RESPONSE", [
                'provider_id'  => $provider->id,
                'http'         => $raw['_http'] ?? null,
                'content_type' => $raw['_content_type'] ?? null,
                'body_head'    => substr((string)($raw['_body'] ?? ''), 0, 500),
            ]);
            return 0;
        }

        $err = data_get($raw, 'ERROR.0.MESSAGE');
        if ($err) {
            logger()->error("GSMHUB {$action} ERROR", [
                'provider_id' => $provider->id,
                'error'       => $err,
            ]);
            return 0;
        }

        $list = $this->extractList($raw);
        if (!is_array($list)) return 0;

        // ✅ GSMHub LIST = Groups => each group has SERVICES
        return $this->upsertGroupedList($provider->id, $serviceType, $list);
    }

    protected function upsertGroupedList(int $apiId, string $serviceType, array $groups): int
    {
        $rows = [];

        foreach ($groups as $group) {
            if (!is_array($group)) continue;

            // بعض الردود تكون: LIST => { "GroupName": {GROUPNAME:..., SERVICES:{...}} }
            $groupName = (string)($group['GROUPNAME'] ?? '');

            $services = $group['SERVICES'] ?? [];
            if (!is_array($services)) continue;

            foreach ($services as $srv) {
                if (!is_array($srv)) continue;

                $remoteId = $srv['SERVICEID'] ?? null;
                if ($remoteId === null) continue;

                $rows[] = [
                    'api_id'          => $apiId,
                    'remote_id'       => (int)$remoteId,
                    'name'            => (string)($srv['SERVICENAME'] ?? ''),
                    'group_name'      => $groupName,
                    'price'           => (float)($srv['CREDIT'] ?? 0),
                    'time'            => (string)($srv['TIME'] ?? ''),
                    'info'            => (string)($srv['INFO'] ?? ''),
                    'additional_data' => json_encode($srv, JSON_UNESCAPED_UNICODE),
                    'updated_at'      => now(),
                    'created_at'      => now(),
                ];
            }
        }

        if (!$rows) return 0;

        // ✅ Upsert دفعات = سريع جدًا
        $table = match ($serviceType) {
            'server' => (new RemoteServerService())->getTable(),
            'file'   => (new RemoteFileService())->getTable(),
            default  => (new RemoteImeiService())->getTable(),
        };

        // تقسيم دفعات لتفادي packet كبير
        $chunks = array_chunk($rows, 800);

        foreach ($chunks as $chunk) {
            DB::table($table)->upsert(
                $chunk,
                ['api_id', 'remote_id'],
                ['name','group_name','price','time','info','additional_data','updated_at']
            );
        }

        return count($rows);
    }
}
