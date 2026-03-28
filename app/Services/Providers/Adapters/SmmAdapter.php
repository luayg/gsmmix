<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteSmmService;
use App\Services\Providers\ProviderAdapterInterface;
use Illuminate\Support\Facades\Http;

class SmmAdapter implements ProviderAdapterInterface
{
    public function type(): string
    {
        return 'smm';
    }

    public function supportsCatalog(string $kind): bool
    {
        return $kind === 'smm';
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        $response = $this->request($provider, [
            'action' => 'balance',
        ]);

        if (isset($response['error']) && trim((string)$response['error']) !== '') {
            throw new \RuntimeException((string)$response['error']);
        }

        $balance = $response['balance'] ?? 0;

        return is_numeric($balance) ? (float)$balance : 0.0;
    }

    public function syncCatalog(ApiProvider $provider, string $kind): int
    {
        if ($kind !== 'smm') {
            return 0;
        }

        $response = $this->request($provider, [
            'action' => 'services',
        ]);

        if (isset($response['error']) && trim((string)$response['error']) !== '') {
            throw new \RuntimeException((string)$response['error']);
        }

        if (!is_array($response)) {
            throw new \RuntimeException('Invalid SMM services response');
        }

        $services = $response;
        if (isset($response['services']) && is_array($response['services'])) {
            $services = $response['services'];
        }

        if (!is_array($services)) {
            throw new \RuntimeException('Invalid SMM services payload');
        }

        RemoteSmmService::query()
            ->where('api_provider_id', $provider->id)
            ->delete();

        $count = 0;

        foreach ($services as $row) {
            if (!is_array($row)) {
                continue;
            }

            $remoteId = trim((string)($row['service'] ?? $row['id'] ?? $row['remote_id'] ?? ''));
            $name     = trim((string)($row['name'] ?? ''));
            $type     = trim((string)($row['type'] ?? 'Default'));
            $category = trim((string)($row['category'] ?? 'SMM'));
            $rate     = $row['rate'] ?? 0;
            $min      = $row['min'] ?? null;
            $max      = $row['max'] ?? null;
            $refill   = (bool)($row['refill'] ?? false);
            $cancel   = (bool)($row['cancel'] ?? false);

            if ($remoteId === '' || $name === '') {
                continue;
            }

            RemoteSmmService::query()->create([
                'api_provider_id'   => $provider->id,
                'group_name'        => $category,
                'remote_id'         => $remoteId,
                'name'              => $name,
                'type'              => $type,
                'category'          => $category,
                'price'             => is_numeric($rate) ? (float)$rate : 0,
                'min'               => is_numeric($min) ? (int)$min : null,
                'max'               => is_numeric($max) ? (int)$max : null,
                'refill'            => $refill,
                'cancel'            => $cancel,
                'time'              => '',
                'additional_fields' => [],
                'additional_data'   => [
                    'raw' => $row,
                ],
                'params'            => [
                    'service' => $remoteId,
                    'type' => $type,
                    'category' => $category,
                    'min' => is_numeric($min) ? (int)$min : null,
                    'max' => is_numeric($max) ? (int)$max : null,
                    'refill' => $refill,
                    'cancel' => $cancel,
                ],
            ]);

            $count++;
        }

        return $count;
    }

    private function request(ApiProvider $provider, array $payload): array
    {
        $url = rtrim((string)$provider->url, '/');
        if ($url === '') {
            throw new \RuntimeException('INVALID URL');
        }

        $key = trim((string)($provider->api_key ?? ''));
        if ($key === '') {
            throw new \RuntimeException('AUTH FAILED');
        }

        $post = array_merge([
            'key' => $key,
        ], $payload);

        try {
            $response = Http::asForm()
                ->timeout(60)
                ->retry(1, 500)
                ->post($url, $post);
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage());
        }

        if (!$response->successful()) {
            throw new \RuntimeException('HTTP ' . $response->status());
        }

        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        $raw = trim((string)$response->body());
        if ($raw === '') {
            throw new \RuntimeException('Empty provider response');
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        throw new \RuntimeException('Invalid JSON response');
    }
}