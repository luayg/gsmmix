<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderAdapterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SmmAdapter implements ProviderAdapterInterface
{
    private function asText($value): string
    {
        if (is_array($value)) {
            $value = implode("\n", array_map(static fn ($v) => (string) $v, $value));
        }

        return trim((string) $value);
    }

    private function pickDescription(array $row): string
    {
        foreach ([
            'description',
            'desc',
            'service_description',
            'service_desc',
            'details',
            'note',
            'notes',
            'info',
            'service_info',
        ] as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $text = $this->asText($row[$key]);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function pickTime(array $row): string
    {
        foreach (['time', 'delivery', 'delivery_time', 'start_time', 'average_time'] as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $text = $this->asText($row[$key]);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function toFloat($value): float
    {
        if ($value === null) {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        $value = str_replace([',', '$', 'USD', 'usd', ' '], '', $value);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function toIntOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeServicesPayload(array $response): array
    {
        if (isset($response['services']) && is_array($response['services'])) {
            return $response['services'];
        }

        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return $response;
    }

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

        if (isset($response['error']) && trim((string) $response['error']) !== '') {
            throw new \RuntimeException((string) $response['error']);
        }

        return $this->toFloat($response['balance'] ?? 0);
    }

    public function syncCatalog(ApiProvider $provider, string $kind): int
    {
        if ($kind !== 'smm') {
            return 0;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        try {
            DB::connection()->disableQueryLog();
        } catch (\Throwable $e) {
            // ignore
        }

        $response = $this->request($provider, [
            'action' => 'services',
        ]);

        if (isset($response['error']) && trim((string) $response['error']) !== '') {
            throw new \RuntimeException((string) $response['error']);
        }

        if (!is_array($response)) {
            throw new \RuntimeException('Invalid SMM services response');
        }

        $services = $this->normalizeServicesPayload($response);

        if (!is_array($services)) {
            throw new \RuntimeException('Invalid SMM services payload');
        }

        $now = now();
        $rowsByRemoteId = [];

        foreach ($services as $row) {
            if (!is_array($row)) {
                continue;
            }

            $remoteId = trim((string) ($row['service'] ?? $row['id'] ?? $row['remote_id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $type = trim((string) ($row['type'] ?? 'Default'));
            $category = trim((string) ($row['category'] ?? 'SMM'));
            $rate = $this->toFloat($row['rate'] ?? 0);
            $min = $this->toIntOrNull($row['min'] ?? null);
            $max = $this->toIntOrNull($row['max'] ?? null);
            $refill = $this->toBoolean($row['refill'] ?? false);
            $cancel = $this->toBoolean($row['cancel'] ?? false);
            $description = $this->pickDescription($row);
            $time = $this->pickTime($row);

            if ($remoteId === '' || $name === '') {
                continue;
            }

            $rowsByRemoteId[$remoteId] = [
                'api_provider_id'   => (int) $provider->id,
                'group_name'        => $category !== '' ? $category : null,
                'remote_id'         => $remoteId,
                'name'              => $name,
                'type'              => $type !== '' ? $type : null,
                'category'          => $category !== '' ? $category : null,
                'price'             => $rate,
                'min'               => $min,
                'max'               => $max,
                'refill'            => $refill ? 1 : 0,
                'cancel'            => $cancel ? 1 : 0,
                'time'              => $time !== '' ? $time : null,
                'additional_fields' => json_encode([], JSON_UNESCAPED_UNICODE),
                'additional_data'   => json_encode([
                    'description' => $description,
                    'time' => $time,
                    'raw' => $row,
                ], JSON_UNESCAPED_UNICODE),
                'params'            => json_encode([
                    'service' => $remoteId,
                    'type' => $type,
                    'category' => $category,
                    'min' => $min,
                    'max' => $max,
                    'refill' => $refill,
                    'cancel' => $cancel,
                ], JSON_UNESCAPED_UNICODE),
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        $rows = array_values($rowsByRemoteId);
        $remoteIds = array_keys($rowsByRemoteId);

        DB::transaction(function () use ($provider, $rows, $remoteIds) {
            if (!empty($rows)) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('remote_smm_services')->upsert(
                        $chunk,
                        ['api_provider_id', 'remote_id'],
                        [
                            'group_name',
                            'name',
                            'type',
                            'category',
                            'price',
                            'min',
                            'max',
                            'refill',
                            'cancel',
                            'time',
                            'additional_fields',
                            'additional_data',
                            'params',
                            'updated_at',
                        ]
                    );
                }

                DB::table('remote_smm_services')
                    ->where('api_provider_id', $provider->id)
                    ->whereNotIn('remote_id', $remoteIds)
                    ->delete();

                return;
            }

            DB::table('remote_smm_services')
                ->where('api_provider_id', $provider->id)
                ->delete();
        });

        return count($rows);
    }

    private function request(ApiProvider $provider, array $payload): array
    {
        $url = rtrim((string) $provider->url, '/');
        if ($url === '') {
            throw new \RuntimeException('INVALID URL');
        }

        $key = trim((string) ($provider->api_key ?? ''));
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

        $raw = trim((string) $response->body());
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