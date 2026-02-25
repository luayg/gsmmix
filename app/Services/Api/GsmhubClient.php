<?php

namespace App\Services\Api;

use App\Exceptions\ProviderApiException;
use App\Models\ApiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GsmhubClient
{
    public function __construct(
        private string $baseUrl,     // ex: https://imei.us/public  OR https://imei.us
        private string $username,
        private string $apiKey,
        private string $requestFormat = 'JSON'
    ) {}

    public static function fromProvider(ApiProvider $provider): self
    {
        // Keep exactly what admin saved, but normalize trailing slash.
        $base = rtrim((string)$provider->url, '/');

        // NOTE: admin may save https://imei.us/public (recommended by doc)
        // or https://imei.us (some installs require this).
        return new self(
            $base,
            (string)$provider->username,
            (string)$provider->api_key,
            'JSON'
        );
    }

    /**
     * Build candidate endpoints to try.
     * 1) {base}/api/index.php  (matches official library when base=https://imei.us/public)
     * 2) If base endswith /public => try parent /api/index.php (many installs)
     */
    private function endpointCandidates(): array
    {
        $base = rtrim($this->baseUrl, '/');

        $candidates = [
            $base . '/api/index.php',
        ];

        // If base is .../public, also try without /public
        if (Str::endsWith($base, '/public')) {
            $parent = rtrim(Str::beforeLast($base, '/public'), '/');
            if ($parent !== '') {
                $candidates[] = $parent . '/api/index.php';
            }
        }

        // De-duplicate while keeping order
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
            // IMPORTANT: imei.us library uses "parameters" (NOT requestxml)
            'parameters'    => $this->buildParametersXml($params),
        ];

        $lastBody = '';
        $lastStatus = 0;

        foreach ($this->endpointCandidates() as $url) {
            $resp = Http::asForm()
                ->timeout(60)
                ->retry(1, 300) // light retry per endpoint
                ->post($url, $payload);

            $lastStatus = $resp->status();
            $lastBody = (string)$resp->body();

            // If 404, try next candidate (this is the exact error you are seeing).
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

            // ✅ SUCCESS
            return $data;
        }

        // If all candidates returned 404:
        throw new ProviderApiException('gsmhub', $action, [
            'http_status' => $lastStatus ?: 404,
            'candidates' => $this->endpointCandidates(),
            'body' => $lastBody,
        ], 'HTTP 404 (endpoint not found on all candidates)');
    }

    // Actions per your doc
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