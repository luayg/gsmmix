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
     * Fast HTTP settings to avoid PHP max_execution_time (60s) on web sync.
     * You can override in provider.params:
     *  - timeout (seconds)          default 10
     *  - connect_timeout (seconds)  default 5
     *  - retries (count)            default 0
     *  - retry_sleep_ms (ms)        default 200
     */
    private function httpOptions(): array
    {
        $timeout = (int) data_get($this->provider, 'params.timeout', 10);
        $connect = (int) data_get($this->provider, 'params.connect_timeout', 5);
        $retries = (int) data_get($this->provider, 'params.retries', 0);
        $sleepMs = (int) data_get($this->provider, 'params.retry_sleep_ms', 200);

        // hard clamp to prevent crazy values
        if ($timeout < 3) $timeout = 3;
        if ($timeout > 30) $timeout = 30;

        if ($connect < 1) $connect = 1;
        if ($connect > 20) $connect = 20;

        if ($retries < 0) $retries = 0;
        if ($retries > 2) $retries = 2;

        if ($sleepMs < 0) $sleepMs = 0;
        if ($sleepMs > 2000) $sleepMs = 2000;

        return [$timeout, $connect, $retries, $sleepMs];
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

        [$timeout, $connect, $retries, $sleepMs] = $this->httpOptions();

        try {
            $req = Http::asForm()
                ->connectTimeout($connect)
                ->timeout($timeout);

            if ($retries > 0) {
                $req = $req->retry($retries, $sleepMs);
            }

            $res = $req->post($this->endpoint(), $payload);

            $json = $res->json();
            return is_array($json)
                ? $json
                : ['ERROR' => [['MESSAGE' => 'Invalid JSON', 'RAW' => $res->body()]]];
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