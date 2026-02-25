<?php

namespace App\Services\Api;

use App\Exceptions\ProviderApiException;
use App\Models\ApiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GsmhubClient
{
    public function __construct(
        private ApiProvider $provider,
        private string $baseUrl,     // ex: https://imei.us/public OR https://imei.us
        private string $username,
        private string $apiKey,
        private string $requestFormat = 'JSON'
    ) {}

    public static function fromProvider(ApiProvider $provider): self
    {
        $base = rtrim((string)$provider->url, '/');

        return new self(
            $provider,
            $base,
            (string)$provider->username,
            (string)$provider->api_key,
            'JSON'
        );
    }

    /**
     * If we already resolved a working endpoint before, use it first.
     */
    private function resolvedEndpoint(): ?string
    {
        $params = $this->provider->params ?? null;

        if (is_string($params) && trim($params) !== '') {
            $decoded = json_decode($params, true);
            $params = is_array($decoded) ? $decoded : null;
        }

        if (is_array($params) && !empty($params['gsmhub_resolved_endpoint'])) {
            $u = trim((string)$params['gsmhub_resolved_endpoint']);
            return $u !== '' ? $u : null;
        }

        return null;
    }

    private function saveResolvedEndpoint(string $endpoint): void
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') return;

        try {
            $params = $this->provider->params ?? [];
            if (is_string($params) && trim($params) !== '') {
                $decoded = json_decode($params, true);
                $params = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($params)) $params = [];

            // only write if changed (avoid DB spam)
            if (($params['gsmhub_resolved_endpoint'] ?? null) === $endpoint) {
                return;
            }

            $params['gsmhub_resolved_endpoint'] = $endpoint;
            $this->provider->params = $params;
            $this->provider->save();
        } catch (\Throwable $e) {
            // caching is optional; do not break API calls if DB write fails
        }
    }

    /**
     * Build candidate endpoints to try.
     * 0) cached resolved endpoint (if any)
     * 1) {base}/api/index.php
     * 2) If base endswith /public => try parent /api/index.php
     */
    private function endpointCandidates(): array
    {
        $base = rtrim($this->baseUrl, '/');

        $candidates = [];

        $cached = $this->resolvedEndpoint();
        if ($cached) $candidates[] = $cached;

        $candidates[] = $base . '/api/index.php';

        if (Str::endsWith($base, '/public')) {
            $parent = rtrim(Str::beforeLast($base, '/public'), '/');
            if ($parent !== '') {
                $candidates[] = $parent . '/api/index.php';
            }
        }

        // unique preserving order
        $unique = [];
        foreach ($candidates as $u) {
            if (!in_array($u, $unique, true)) $unique[] = $u;
        }
        return $unique;
    }

    public function call(string $action, array $params = []): array
    {
        $payload = [
            'username'      => $this->username,
            'apiaccesskey'  => $this->apiKey,
            'action'        => $action,
            'requestformat' => $this->requestFormat,
            'parameters'    => $this->buildParametersXml($params),
        ];

        $lastBody = '';
        $lastStatus = 0;

        foreach ($this->endpointCandidates() as $url) {
            $resp = Http::asForm()
                ->timeout(60)
                ->retry(1, 300)
                ->post($url, $payload);

            $lastStatus = $resp->status();
            $lastBody = (string)$resp->body();

            // if 404 => try next candidate
            if ($resp->status() === 404) {
                continue;
            }

            if (!$resp->successful()) {
                throw new ProviderApiException('gsmhub', $action, [
                    'http_status' => $resp->status(),
                    'endpoint' => $url,
                    'body' => $lastBody,
                ], "HTTP {$resp->status()}");
            }

            $data = json_decode(trim($lastBody), true);

            if (!is_array($data)) {
                $xml = @simplexml_load_string($lastBody);
                if ($xml !== false) {
                    $data = json_decode(json_encode($xml), true);
                }
            }

            if (!is_array($data)) {
                throw new ProviderApiException('gsmhub', $action, [
                    'endpoint' => $url,
                    'raw' => $lastBody,
                ], 'Invalid response');
            }

            if (isset($data['ERROR'])) {
                $msg = data_get($data, 'ERROR.0.FULL_DESCRIPTION')
                    ?: data_get($data, 'ERROR.0.MESSAGE')
                    ?: 'Unknown API error';

                throw new ProviderApiException('gsmhub', $action, $data, (string)$msg);
            }

            // ✅ SUCCESS: cache working endpoint
            $this->saveResolvedEndpoint($url);

            return $data;
        }

        throw new ProviderApiException('gsmhub', $action, [
            'http_status' => $lastStatus ?: 404,
            'candidates' => $this->endpointCandidates(),
            'body' => $lastBody,
        ], 'HTTP 404 (endpoint not found on all candidates)');
    }

    // Actions per imei.us doc
    public function accountInfo(): array { return $this->call('accountinfo'); }
    public function imeiServiceList(): array { return $this->call('imeiservicelist'); }
    public function serverServiceList(): array { return $this->call('serverservicelist'); }
    public function fileServiceList(): array { return $this->call('fileservicelist'); }

    public function placeImeiOrder(array $params): array { return $this->call('placeimeiorder', $params); }
    public function getImeiOrder(string $id): array { return $this->call('getimeiorder', ['ID' => $id]); }

    public function placeServerOrder(array $params): array { return $this->call('placeserverorder', $params); }
    public function getServerOrder(string $id): array { return $this->call('getserverorder', ['ID' => $id]); }

    public function placeFileOrder(array $params): array { return $this->call('placefileorder', $params); }
    public function getFileOrder(string $id): array { return $this->call('getfileorder', ['ID' => $id]); }

    private function buildParametersXml(array $params): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElement('PARAMETERS');
        $xml->appendChild($root);

        foreach ($params as $k => $v) {
            $tag = strtoupper((string)$k);
            $node = $xml->createElement($tag);
            $node->appendChild($xml->createTextNode((string)$v));
            $root->appendChild($node);
        }

        return $xml->saveHTML($root) ?: '<PARAMETERS></PARAMETERS>';
    }
}