<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Services\Providers\ProviderAdapterInterface;
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
     * لكن endpoint الحقيقي للـ API عادةً: https://imei.us/api/index.php
     *
     * ✅ يدعم الحالات:
     * - https://imei.us/public
     * - https://imei.us
     * - https://imei.us/api/index.php
     * - أي رابط ينتهي بملف php يعتبر endpoint جاهز.
     */
    protected function endpoint(ApiProvider $p): string
    {
        $base = rtrim((string) $p->url, '/');

        // إذا المستخدم كتب ملف php مباشرة
        if (preg_match('~/[^/]+\.php$~i', $base)) {
            return $base;
        }

        // إذا انتهى بـ /public -> احذف public من المسار
        if (preg_match('~/public$~i', $base)) {
            $base = preg_replace('~/public$~i', '', $base);
            $base = rtrim((string) $base, '/');
        }

        return $base . '/api/index.php';
    }

    protected function buildParametersXml(array $params): string
    {
        if (empty($params)) return '';

        $xml = '<PARAMETERS>';
        foreach ($params as $k => $v) {
            $tag = strtoupper((string) $k);
            $val = htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
            ->timeout(60)
            ->post($url, $payload);

        // Debug HTTP
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

    /**
     * بعض المزودين حساسون لحالة اسم action (lower/upper)
     * هنا نعمل fallback بسيط.
     */
    protected function callWithFallback(ApiProvider $p, string $action, array $params = []): array
    {
        $raw = $this->call($p, $action, $params);

        // Non-JSON response
        if (isset($raw['_body'])) return $raw;

        $err  = data_get($raw, 'ERROR.0.MESSAGE');
        $list = $this->extractList($raw);

        if ($err || (is_array($list) && count($list) === 0)) {
            $alt = ucfirst(strtolower($action));
            if ($alt !== $action) {
                $raw2 = $this->call($p, $alt, $params);
                if (!isset($raw2['_body'])) {
                    $err2  = data_get($raw2, 'ERROR.0.MESSAGE');
                    $list2 = $this->extractList($raw2);
                    if (!$err2 && is_array($list2) && count($list2) > 0) return $raw2;
                }
            }
        }

        return $raw;
    }

    /**
     * استخراج LIST من أكثر من شكل شائع.
     */
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
        $raw = $this->callWithFallback($provider, 'accountinfo');

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

        // حسب ما يظهر في response عندك: SUCCESS[0].AccoutInfo.credit
        $creditraw = data_get($raw, 'SUCCESS.0.AccoutInfo.creditraw');
        if ($creditraw !== null) return (float) $creditraw;

        $credit = data_get($raw, 'SUCCESS.0.AccoutInfo.credit');
        return $credit !== null ? (float) $credit : 0.0;
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

        $raw = $this->callWithFallback($provider, $action);

        logger()->info('GSMHUB RAW', [
            'provider_id' => $provider->id,
            'action'      => $action,
            'raw_keys'    => is_array($raw) ? array_keys($raw) : gettype($raw),
        ]);

        if (isset($raw['_body'])) {
            logger()->error("GSMHUB {$action} NON_JSON_RESPONSE", [
                'provider_id'   => $provider->id,
                'http'          => $raw['_http'] ?? null,
                'content_type'  => $raw['_content_type'] ?? null,
                'body_head'     => substr((string)($raw['_body'] ?? ''), 0, 500),
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
        if (!is_array($list)) {
            logger()->error("GSMHUB {$action} INVALID_LIST", [
                'provider_id' => $provider->id,
            ]);
            return 0;
        }

        // LIST قد يكون:
        // 1) Groups: LIST[groupName] = {GROUPNAME, SERVICES{...}}
        // 2) Map خدمات مباشر: LIST[serviceId] = {SERVICEID,...}
        if ($this->looksLikeServiceMap($list)) {
            return $this->upsertServiceMap($provider->id, $serviceType, $list);
        }

        return $this->upsertGroupedList($provider->id, $serviceType, $list);
    }

    protected function looksLikeServiceMap(array $list): bool
    {
        $first = reset($list);
        return is_array($first) && (isset($first['SERVICEID']) || isset($first['SERVICENAME']));
    }

    protected function upsertServiceMap(int $apiId, string $serviceType, array $map): int
    {
        $rows = [];
        foreach ($map as $srv) {
            if (!is_array($srv)) continue;

            $remoteId = (string)($srv['SERVICEID'] ?? $srv['id'] ?? '');
            if ($remoteId === '') continue;

            $rows[] = [
                'api_id'          => $apiId,
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

        return $this->bulkUpsert($serviceType, $rows);
    }

    protected function upsertGroupedList(int $apiId, string $serviceType, array $groups): int
    {
        $rows = [];

        foreach ($groups as $group) {
            if (!is_array($group)) continue;

            $groupName = (string)($group['GROUPNAME'] ?? $group['group'] ?? '');

            // بعض الردود: group['SERVICES'] = {id => srv}
            $services = $group['SERVICES'] ?? $group['LIST'] ?? $group['services'] ?? $group['list'] ?? [];
            if (!is_array($services)) continue;

            foreach ($services as $srv) {
                if (!is_array($srv)) continue;

                $remoteId = (string)($srv['SERVICEID'] ?? $srv['id'] ?? '');
                if ($remoteId === '') continue;

                $rows[] = [
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
        }

        return $this->bulkUpsert($serviceType, $rows);
    }

    /**
     * ✅ Upsert دفعات لتسريع المزامنة ومنع timeout
     * يفترض وجود unique على (api_id, remote_id) أو على الأقل الاعتماد عليه منطقياً.
     */
    protected function bulkUpsert(string $serviceType, array $rows): int
    {
        if (empty($rows)) return 0;

        $model = match ($serviceType) {
            'imei'   => new RemoteImeiService(),
            'server' => new RemoteServerService(),
            'file'   => new RemoteFileService(),
            default  => null,
        };

        if (!$model) return 0;

        $count = 0;
        foreach (array_chunk($rows, 300) as $chunk) {
            $model->newQuery()->upsert(
                $chunk,
                ['api_id', 'remote_id'],
                ['name', 'group_name', 'price', 'time', 'info', 'additional_data', 'updated_at']
            );
            $count += count($chunk);
        }

        return $count;
    }
}
