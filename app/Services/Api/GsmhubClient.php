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
        private string $baseUrl,
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
     * We want to be aligned with the doc: base URL is https://imei.us/public
     * Most installs expose the API script under /public/api.php
     */
    private function endpointCandidates(): array
    {
        $base = rtrim($this->baseUrl, '/');

        $candidates = [];

        // ✅ Preferred (document-friendly): /public/api.php
        if (Str::endsWith($base, '/public')) {
            $candidates[] = $base . '/api.php';
        }

        // Secondary: /public/api/index.php (some installs)
        if (Str::endsWith($base, '/public')) {
            $candidates[] = $base . '/api/index.php';
        }

        // Fallbacks (older/other setups)
        $candidates[] = $base . '/api.php';
        $candidates[] = $base . '/api/index.php';

        // If base endswith /public, also try parent (NOT preferred, but last resort)
        if (Str::endsWith($base, '/public')) {
            $parent = rtrim(Str::beforeLast($base, '/public'), '/');
            if ($parent !== '') {
                $candidates[] = $parent . '/api.php';
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
            // imei.us style:
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

            // ✅ SUCCESS:
            // Do NOT persist resolved endpoint outside /public to keep doc-consistency.
            // If you still want caching, cache ONLY if url starts with baseUrl (doc-friendly).
            $this->saveResolvedEndpointIfDocFriendly($url);

            return $data;
        }

        throw new ProviderApiException('gsmhub', $action, [
            'http_status' => $lastStatus ?: 404,
            'candidates' => $this->endpointCandidates(),
            'body' => $lastBody,
        ], 'HTTP 404 (endpoint not found on all candidates)');
    }

    private function saveResolvedEndpointIfDocFriendly(string $endpoint): void
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') return;

        $base = rtrim($this->baseUrl, '/');

        // Cache ONLY if endpoint is under the provided base URL (so docs stay true).
        if (!Str::startsWith($endpoint, $base)) {
            return;
        }

        try {
            $params = $this->provider->params ?? [];
            if (is_string($params) && trim($params) !== '') {
                $decoded = json_decode($params, true);
                $params = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($params)) $params = [];

            $params['gsmhub_resolved_endpoint'] = $endpoint;

            $this->provider->params = $params;
            $this->provider->save();
        } catch (\Throwable $e) {
            // ignore
        }
    }

    // Actions
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