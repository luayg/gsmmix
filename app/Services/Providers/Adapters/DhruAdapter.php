<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Services\Providers\ProviderAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DhruAdapter implements ProviderAdapterInterface
{
    public function type(): string
    {
        return 'dhru';
    }

    public function supportsCatalog(string $serviceType): bool
    {
        return in_array(strtolower($serviceType), ['imei', 'server', 'file'], true);
    }

    /**
     * DHRU endpoint غالباً:
     *  - https://example.com/api/index.php
     * بعض المستخدمين يكتبون رابط فيه index.php جاهز
     */
    protected function endpoint(ApiProvider $p): string
    {
        $base = rtrim((string)($p->url ?? ''), '/');

        // إذا المستخدم كتب ملف php مباشرة
        if ($base !== '' && preg_match('~/[^/]+\.php$~i', $base)) {
            return $base;
        }

        // الافتراضي
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
            'action'        => trim($action),
            'requestformat' => 'JSON',
            'parameters'    => $this->buildParametersXml($params),
        ];

        $res = Http::asForm()
            ->timeout(60)
            ->retry(2, 300)
            ->post($url, $payload);

        Log::info('DHRU HTTP', [
            'provider_id'   => $p->id,
            'url'           => $url,
            'action'        => $action,
            'http'          => $res->status(),
            'content_type'  => $res->header('content-type'),
            'body_head'     => substr($res->body(), 0, 300),
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

    protected function looksLikeServiceMap(array $list): bool
    {
        $first = reset($list);
        return is_array($first) && (isset($first['SERVICEID']) || isset($first['SERVICENAME']));
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        $raw = $this->call($provider, 'accountinfo');

        if (isset($raw['_body'])) return 0.0;

        $err = data_get($raw, 'ERROR.0.MESSAGE');
        if ($err) {
            Log::error('DHRU accountinfo ERROR', [
                'provider_id' => $provider->id,
                'error' => $err,
            ]);
            return 0.0;
        }

        // بعض DHRU يرجع:
        // SUCCESS[0].AccoutInfo.credit
        $credit = data_get($raw, 'SUCCESS.0.AccoutInfo.credit');
        if ($credit !== null) return (float)$credit;

        // fallback
        $credit2 = data_get($raw, 'SUCCESS.0.AccoutInfo.creditraw');
        return $credit2 !== null ? (float)$credit2 : 0.0;
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

        if (isset($raw['_body'])) {
            Log::error("DHRU {$action} NON_JSON_RESPONSE", [
                'provider_id' => $provider->id,
                'http' => $raw['_http'] ?? null,
                'content_type' => $raw['_content_type'] ?? null,
                'body_head' => substr((string)($raw['_body'] ?? ''), 0, 300),
            ]);
            return 0;
        }

        $err = data_get($raw, 'ERROR.0.MESSAGE');
        if ($err) {
            Log::error("DHRU {$action} ERROR", [
                'provider_id' => $provider->id,
                'error' => $err,
            ]);
            return 0;
        }

        $list = $this->extractList($raw);
        if (!is_array($list)) {
            Log::error("DHRU {$action} INVALID_LIST", [
                'provider_id' => $provider->id,
                'raw_keys' => array_keys($raw),
            ]);
            return 0;
        }

        $rows = [];
        $now = now();

        // 1) لو كانت Map خدمات مباشرة
        if ($this->looksLikeServiceMap($list)) {
            foreach ($list as $srv) {
                if (!is_array($srv)) continue;

                $remoteId = (string)($srv['SERVICEID'] ?? '');
                if ($remoteId === '') continue;

                $rows[] = [
                    'api_id'          => $provider->id,
                    'remote_id'       => $remoteId,
                    'group_name'      => (string)($srv['GROUPNAME'] ?? ''),
                    'name'            => (string)($srv['SERVICENAME'] ?? ''),
                    'price'           => (float)($srv['CREDIT'] ?? 0),
                    'service_fee'     => (float)($srv['CREDIT_SERVICE_FEE'] ?? 0),
                    'time'            => (string)($srv['TIME'] ?? ''),
                    'info'            => (string)($srv['INFO'] ?? ''),
                    'params'          => isset($srv['REQUIRED']) ? json_encode(['required' => $srv['REQUIRED']], JSON_UNESCAPED_UNICODE) : null,
                    'additional_data' => json_encode($srv, JSON_UNESCAPED_UNICODE),
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }

            return $this->upsertRows($serviceType, $rows);
        }

        // 2) grouped list
        foreach ($list as $groupKey => $group) {
            if (!is_array($group)) continue;

            $groupName = (string)($group['GROUPNAME'] ?? (is_string($groupKey) ? $groupKey : ''));

            $services = $group['SERVICES'] ?? $group['LIST'] ?? $group['services'] ?? $group['list'] ?? [];
            if (!is_array($services)) continue;

            foreach ($services as $srv) {
                if (!is_array($srv)) continue;

                $remoteId = (string)($srv['SERVICEID'] ?? '');
                if ($remoteId === '') continue;

                $rows[] = [
                    'api_id'          => $provider->id,
                    'remote_id'       => $remoteId,
                    'group_name'      => $groupName,
                    'name'            => (string)($srv['SERVICENAME'] ?? ''),
                    'price'           => (float)($srv['CREDIT'] ?? 0),
                    'service_fee'     => (float)($srv['CREDIT_SERVICE_FEE'] ?? 0),
                    'time'            => (string)($srv['TIME'] ?? ''),
                    'info'            => (string)($srv['INFO'] ?? ''),
                    'params'          => isset($srv['REQUIRED']) ? json_encode(['required' => $srv['REQUIRED']], JSON_UNESCAPED_UNICODE) : null,
                    'additional_data' => json_encode($srv, JSON_UNESCAPED_UNICODE),
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
        }

        return $this->upsertRows($serviceType, $rows);
    }

    protected function upsertRows(string $serviceType, array $rows): int
    {
        if (empty($rows)) return 0;

        $updateCols = [
            'group_name', 'name', 'price', 'service_fee', 'time', 'info', 'params', 'additional_data', 'updated_at',
        ];

        $total = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            if ($serviceType === 'imei') {
                RemoteImeiService::upsert($chunk, ['api_id', 'remote_id'], $updateCols);
            } elseif ($serviceType === 'server') {
                RemoteServerService::upsert($chunk, ['api_id', 'remote_id'], $updateCols);
            } else {
                RemoteFileService::upsert($chunk, ['api_id', 'remote_id'], $updateCols);
            }
            $total += count($chunk);
        }

        return $total;
    }
}
