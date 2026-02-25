<?php

namespace App\Services\Api;

use App\Exceptions\ProviderApiException;
use App\Models\ApiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GsmhubClient
{
    public function __construct(
        private string $endpoint,
        private string $username,
        private string $apiKey,
        private string $requestFormat = 'JSON'
    ) {}

    public static function fromProvider(ApiProvider $provider): self
    {
        // ✅ endpoint priority:
        // 1) params.endpoint (absolute or relative)
        // 2) if provider->url already contains .php -> use as-is
        // 3) default -> {base}/api.php  (per imei.us docs)
        $base = rtrim((string)$provider->url, '/');

        $params = $provider->params ?? null;
        if (is_string($params) && trim($params) !== '') {
            $decoded = json_decode($params, true);
            if (is_array($decoded)) $params = $decoded;
        }

        if (is_array($params) && !empty($params['endpoint'])) {
            $ep = trim((string)$params['endpoint']);
            if ($ep !== '') {
                if (Str::startsWith($ep, ['http://', 'https://'])) {
                    return new self($ep, (string)$provider->username, (string)$provider->api_key, 'JSON');
                }
                return new self(rtrim($base, '/') . '/' . ltrim($ep, '/'), (string)$provider->username, (string)$provider->api_key, 'JSON');
            }
        }

        if (Str::contains($base, ['.php', 'api.php'])) {
            return new self($base, (string)$provider->username, (string)$provider->api_key, 'JSON');
        }

        return new self($base . '/api.php', (string)$provider->username, (string)$provider->api_key, 'JSON');
    }

    public function accountInfo(): array
    {
        return $this->call('accountinfo');
    }

    public function imeiServiceList(): array
    {
        return $this->call('imeiservicelist');
    }

    public function serverServiceList(): array
    {
        return $this->call('serverservicelist');
    }

    public function fileServiceList(): array
    {
        return $this->call('fileservicelist');
    }

    public function call(string $action, array $params = []): array
    {
        $payload = [
            'username'      => $this->username,
            'apiaccesskey'  => $this->apiKey,
            'requestformat' => $this->requestFormat,
            'action'        => $action,
        ];

        if (!empty($params)) {
            $payload['requestxml'] = $this->buildRequestXml($params);
        }

        $resp = Http::asForm()
            ->timeout(60)
            ->retry(2, 500)
            ->post($this->endpoint, $payload);

        if (!$resp->successful()) {
            throw new ProviderApiException('gsmhub', $action, [
                'http_status' => $resp->status(),
                'body' => $resp->body(),
                'endpoint' => $this->endpoint,
            ], "HTTP {$resp->status()}");
        }

        $body = trim((string)$resp->body());
        $data = json_decode($body, true);

        if (!is_array($data)) {
            $xml = @simplexml_load_string($body);
            if ($xml !== false) {
                $data = json_decode(json_encode($xml), true);
            }
        }

        if (!is_array($data)) {
            throw new ProviderApiException('gsmhub', $action, ['body' => $body, 'endpoint' => $this->endpoint], 'Invalid response');
        }

        if (isset($data['ERROR'])) {
            $msg = data_get($data, 'ERROR.0.FULL_DESCRIPTION')
                ?: data_get($data, 'ERROR.0.MESSAGE')
                ?: 'Unknown API error';

            throw new ProviderApiException('gsmhub', $action, $data, (string)$msg);
        }

        return $data;
    }

    private function buildRequestXml(array $params): string
    {
        $xml = new \SimpleXMLElement('<PARAMETERS/>');

        foreach ($params as $key => $value) {
            $xml->addChild($key, htmlspecialchars((string)$value));
        }

        return $xml->asXML() ?: '';
    }
}