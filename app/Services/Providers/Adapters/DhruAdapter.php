<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Services\Providers\ProviderAdapterInterface;
use Illuminate\Support\Facades\Http;

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

    protected function endpoint(ApiProvider $p): string
    {
        // مثال شائع: https://example.com/dhru/api/index.php
        // إذا المستخدم وضع endpoint كامل نستخدمه كما هو.
        $base = rtrim((string)$p->url, '/');
        if (preg_match('~/[^/]+\.php$~i', $base)) return $base;

        // الافتراضي - عدّل حسب مشروعك إن كان مختلف
        return $base . '/api/index.php';
    }

    protected function call(ApiProvider $p, string $action, array $params = []): array
    {
        $url = $this->endpoint($p);

        // DHru غالبًا يستخدم form-data أو application/x-www-form-urlencoded
        $payload = array_merge([
            'username' => (string)($p->username ?? ''),
            'api_key'  => (string)($p->api_key ?? ''),
            'action'   => $action,
        ], $params);

        $res = Http::asForm()
            ->timeout(120)
            ->post($url, $payload);

        logger()->info('DHRU HTTP', [
            'provider_id'  => $p->id,
            'url'          => $url,
            'action'       => $action,
            'http'         => $res->status(),
            'content_type' => $res->header('content-type'),
            'body_head'    => substr($res->body(), 0, 500),
        ]);

        $json = $res->json();
        if (is_array($json)) return $json;

        return [
            '_http' => $res->status(),
            '_body' => $res->body(),
        ];
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        // عدّل اسم الأكشن حسب DHru الحقيقي في مشروعك إن كان مختلف
        $raw = $this->call($provider, 'accountinfo');

        if (isset($raw['_body'])) return 0.0;

        // حاول استخراج الرصيد من أكثر من مسار محتمل
        $bal = data_get($raw, 'SUCCESS.balance')
            ?? data_get($raw, 'SUCCESS.0.balance')
            ?? data_get($raw, 'balance')
            ?? data_get($raw, 'SUCCESS.0.AccoutInfo.credit')
            ?? null;

        return $bal !== null ? (float)$bal : 0.0;
    }

    public function syncCatalog(ApiProvider $provider, string $serviceType): int
    {
        $serviceType = strtolower($serviceType);

        // عدّل الأكشن حسب مزود DHru الحقيقي عندك
        $action = match ($serviceType) {
            'imei'   => 'imeiservicelist',
            'server' => 'serverservicelist',
            'file'   => 'fileservicelist',
            default  => null,
        };

        if (!$action) return 0;

        $raw = $this->call($provider, $action);

        if (isset($raw['_body'])) return 0;

        // استخراج LIST شائع
        $list = data_get($raw, 'SUCCESS.0.LIST')
            ?? data_get($raw, 'SUCCESS.LIST')
            ?? data_get($raw, 'LIST')
            ?? null;

        if (!is_array($list)) {
            logger()->error('DHRU INVALID_LIST', [
                'provider_id' => $provider->id,
                'action'      => $action,
            ]);
            return 0;
        }

        // قد يكون list = [ [..], [..] ] أو map
        $rows = [];
        foreach ($list as $k => $srv) {
            if (!is_array($srv)) continue;

            $remoteId = (string)($srv['SERVICEID'] ?? $srv['service_id'] ?? $srv['id'] ?? $k);
            if ($remoteId === '') continue;

            $rows[] = [
                'api_id'          => $provider->id,
                'remote_id'       => $remoteId,
                'name'            => (string)($srv['SERVICENAME'] ?? $srv['name'] ?? ''),
                'group_name'      => (string)($srv['GROUPNAME'] ?? $srv['group'] ?? ''),
                'price'           => (float)($srv['CREDIT'] ?? $srv['price'] ?? 0),
                'time'            => (string)($srv['TIME'] ?? $srv['time'] ?? ''),
                'info'            => (string)($srv['INFO'] ?? $srv['info'] ?? ''),
                'additional_data' => json_encode($srv, JSON_UNESCAPED_UNICODE),
                'updated_at'      => now(),
                'created_at'      => now(),
            ];
        }

        return $this->bulkUpsert($serviceType, $rows);
    }

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
