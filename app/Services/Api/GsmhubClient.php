<?php

namespace App\Services\Api;

use App\Exceptions\ProviderApiException;
use App\Models\ApiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GsmhubClient
{
    public function __construct(
        private string $baseUrl,     // ex: https://imei.us/public
        private string $username,
        private string $apiKey,
        private string $requestFormat = 'JSON'
    ) {}

    public static function fromProvider(ApiProvider $provider): self
    {
        // Per provider note: API URL must be https://imei.us/public
        $base = rtrim((string)$provider->url, '/');

        // If admin mistakenly saved https://imei.us (without /public), append it.
        if (!Str::endsWith($base, '/public')) {
            $base .= '/public';
        }

        return new self(
            $base,
            (string)$provider->username,
            (string)$provider->api_key,
            'JSON'
        );
    }

    public function endpoint(): string
    {
        // Official library uses: IMEI_URL . '/api/index.php'
        return rtrim($this->baseUrl, '/') . '/api/index.php';
    }

    public function call(string $action, array $params = []): array
    {
        $payload = [
            'username'      => $this->username,
            'apiaccesskey'  => $this->apiKey,
            'action'        => $action,
            'requestformat' => $this->requestFormat,
        ];

        // Official library uses POST field name: "parameters" (NOT requestxml)
        if (!empty($params)) {
            $payload['parameters'] = $this->buildParametersXml($params);
        } else {
            // Some APIs accept empty parameters, library still sends an empty <PARAMETERS/>
            $payload['parameters'] = '<PARAMETERS></PARAMETERS>';
        }

        $url = $this->endpoint();

        $resp = Http::asForm()
            ->timeout(60)
            ->retry(2, 500)
            ->post($url, $payload);

        $body = (string)$resp->body();

        if (!$resp->successful()) {
            throw new ProviderApiException('gsmhub', $action, [
                'http_status' => $resp->status(),
                'endpoint' => $url,
                'body' => $body,
            ], "HTTP {$resp->status()}");
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            // fallback XML
            $xml = @simplexml_load_string($body);
            if ($xml !== false) {
                $data = json_decode(json_encode($xml), true);
            }
        }

        if (!is_array($data)) {
            throw new ProviderApiException('gsmhub', $action, [
                'endpoint' => $url,
                'raw' => $body,
            ], 'Invalid response');
        }

        if (isset($data['ERROR'])) {
            $msg = data_get($data, 'ERROR.0.FULL_DESCRIPTION')
                ?: data_get($data, 'ERROR.0.MESSAGE')
                ?: 'Unknown API error';

            throw new ProviderApiException('gsmhub', $action, $data, (string)$msg);
        }

        return $data;
    }

    // Actions (per your doc)
    public function accountInfo(): array { return $this->call('accountinfo', []); }
    public function imeiServiceList(): array { return $this->call('imeiservicelist', []); }
    public function serverServiceList(): array { return $this->call('serverservicelist', []); }
    public function fileServiceList(): array { return $this->call('fileservicelist', []); }

    public function placeImeiOrder(array $params): array { return $this->call('placeimeiorder', $params); }
    public function getImeiOrder(string $id): array { return $this->call('getimeiorder', ['ID' => $id]); }

    public function placeServerOrder(array $params): array { return $this->call('placeserverorder', $params); }
    public function getServerOrder(string $id): array { return $this->call('getserverorder', ['ID' => $id]); }

    public function placeFileOrder(array $params): array { return $this->call('placefileorder', $params); }
    public function getFileOrder(string $id): array { return $this->call('getfileorder', ['ID' => $id]); }

    private function buildParametersXml(array $params): string
    {
        // Official library creates <PARAMETERS><KEY>VALUE</KEY>...</PARAMETERS>
        // and uppercases tags
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElement('PARAMETERS');
        $xml->appendChild($root);

        foreach ($params as $k => $v) {
            $tag = strtoupper((string)$k);
            $node = $xml->createElement($tag);
            $node->appendChild($xml->createTextNode((string)$v));
            $root->appendChild($node);
        }

        // Equivalent to library's saveHTML() output.
        return $xml->saveHTML($root) ?: '<PARAMETERS></PARAMETERS>';
    }
}