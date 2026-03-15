<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Services\Providers\ProviderAdapterInterface;
use Illuminate\Support\Facades\Http;

class SimpleLinkAdapter implements ProviderAdapterInterface
{
    public function type(): string
    {
        return 'simple_link';
    }

    public function supportsCatalog(string $kind): bool
    {
        return in_array($kind, ['imei', 'server', 'file'], true);
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        $params = $this->params($provider);
        $balanceUrl = trim((string)($params['balance_url'] ?? ''));

        if ($balanceUrl === '') {
            return 0.0;
        }

        $method = $this->method($provider);

        try {
            $response = $method === 'GET'
                ? Http::timeout(30)->get($balanceUrl)
                : Http::timeout(30)->asForm()->post($balanceUrl, []);

            if (!$response->successful()) {
                return 0.0;
            }

            $json = $response->json();
            if (!is_array($json)) {
                return 0.0;
            }

            $balance = $json['balance'] ?? $json['credit'] ?? $json['amount'] ?? 0;
            return is_numeric($balance) ? (float)$balance : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    public function syncCatalog(ApiProvider $provider, string $kind): int
    {
        $url = trim((string)$provider->url);
        if ($url === '') {
            throw new \RuntimeException('SIMPLE LINK URL IS EMPTY');
        }

        $method = $this->method($provider);

        $payload = [
            'action' => 'services',
            'kind'   => $kind,
        ];

        $response = $method === 'GET'
            ? Http::timeout(60)->get($url, $payload)
            : Http::timeout(60)->asForm()->post($url, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('Simple Link endpoint returned HTTP ' . $response->status());
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new \RuntimeException('Simple Link endpoint did not return valid JSON');
        }

        if (array_key_exists('success', $json) && !$json['success']) {
            $err = trim((string)($json['error'] ?? 'Simple Link service list failed'));
            throw new \RuntimeException($err !== '' ? $err : 'Simple Link service list failed');
        }

        $services = [];
        if (isset($json['services']) && is_array($json['services'])) {
            $services = $json['services'];
        } elseif (array_is_list($json)) {
            $services = $json;
        }

        [$modelClass] = match ($kind) {
            'imei'   => [RemoteImeiService::class],
            'server' => [RemoteServerService::class],
            'file'   => [RemoteFileService::class],
            default  => [RemoteImeiService::class],
        };

        $modelClass::query()->where('api_provider_id', $provider->id)->delete();

        $count = 0;

        foreach ($services as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowKind = strtolower(trim((string)($row['kind'] ?? $kind)));
            if ($rowKind !== $kind) {
                continue;
            }

            $remoteId = trim((string)($row['id'] ?? $row['remote_id'] ?? $row['service'] ?? $row['service_id'] ?? ''));
            $name = trim((string)($row['name'] ?? $row['title'] ?? ''));
            $group = trim((string)($row['group'] ?? $row['group_name'] ?? 'Simple Link'));
            $time = trim((string)($row['time'] ?? ''));
            $price = (float)($row['price'] ?? $row['credit'] ?? 0);
            $allowedExtensions = trim((string)($row['allowed_extensions'] ?? ''));
            $additionalFields = $row['additional_fields'] ?? [];

            if ($remoteId === '' || $name === '') {
                continue;
            }

            $insert = [
                'api_provider_id'   => $provider->id,
                'group_name'        => $group,
                'remote_id'         => $remoteId,
                'name'              => $name,
                'price'             => $price,
                'time'              => $time,
                'additional_fields' => is_array($additionalFields) ? $additionalFields : [],
            ];

            if ($kind === 'file') {
                $insert['allowed_extensions'] = $allowedExtensions;
            }

            $modelClass::query()->create($insert);
            $count++;
        }

        return $count;
    }

    private function params(ApiProvider $provider): array
    {
        $params = $provider->params ?? [];
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            $params = is_array($decoded) ? $decoded : [];
        }

        return is_array($params) ? $params : [];
    }

    private function method(ApiProvider $provider): string
    {
        $params = $this->params($provider);
        $method = strtoupper(trim((string)($params['method'] ?? 'POST')));

        return in_array($method, ['GET', 'POST'], true) ? $method : 'POST';
    }
}