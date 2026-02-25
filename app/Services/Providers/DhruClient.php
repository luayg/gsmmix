<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DhruClient
{
    public function __construct(protected ApiProvider $provider) {}

    public static function fromProvider(ApiProvider $provider): self
    {
        return new self($provider);
    }

    /**
     * Resolve the API endpoint.
     *
     * Most DHRU-style suppliers use: {base}/api/index.php
     * Some store the full endpoint already in provider->url.
     * You can also override via $provider->params['endpoint'] (relative or absolute).
     */
    private function endpoint(): string
    {
        $base = rtrim((string)$this->provider->url, '/');

        $params = $this->provider->params ?? null;
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            if (is_array($decoded)) $params = $decoded;
        }
        if (is_array($params) && !empty($params['endpoint'])) {
            $ep = (string)$params['endpoint'];
            if (Str::startsWith($ep, ['http://', 'https://'])) return $ep;

            return rtrim($base, '/') . '/' . ltrim($ep, '/');
        }

        // If url already points to a php endpoint, use it as-is.
        if (Str::contains($base, ['index.php', '.php'])) {
            return $base;
        }

        return $base . '/api/index.php';
    }

    /**
     * Common DHRU-style request:
     * - username
     * - apiaccesskey
     * - action
     * - requestformat=JSON
     * - parameters=base64(json_encode([...]))   (for most actions)
     */
    public function call(string $action, array $parameters = []): array
    {
        $payload = [
            'username'      => (string)$this->provider->username,
            'apiaccesskey'  => (string)$this->provider->api_key,
            'action'        => $action,
            'requestformat' => 'JSON',
        ];

        if (!empty($parameters)) {
            $payload['parameters'] = base64_encode(json_encode($parameters, JSON_UNESCAPED_UNICODE));
        }

        try {
            $res = Http::asForm()
                ->timeout(60)
                ->retry(2, 300) // 2 retries, 300ms backoff
                ->post($this->endpoint(), $payload);

            $json = $res->json();
            return is_array($json) ? $json : ['ERROR' => [['MESSAGE' => 'Invalid JSON', 'RAW' => $res->body()]]];
        } catch (\Throwable $e) {
            return ['ERROR' => [['MESSAGE' => $e->getMessage()]]];
        }
    }

    public function accountInfo(): array
    {
        return $this->call('accountinfo');
    }

    /** Returns services + groups for IMEI & SERVER. */
    public function getAllServicesAndGroups(): array
    {
        return $this->call('getallservicesandgroups');
    }

    /** Returns file services list. */
    public function getFileServices(): array
    {
        return $this->call('fileservicelist');
    }
}