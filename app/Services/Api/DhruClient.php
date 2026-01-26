<?php

namespace App\Services\Api;

use App\Exceptions\ProviderApiException;
use App\Models\ApiProvider;
use Illuminate\Support\Facades\Http;

class DhruClient
{
    public function __construct(
        private string $endpoint,
        private string $username,
        private string $apiKey,
        private string $requestFormat = 'JSON'
    ) {}

    public static function fromProvider(ApiProvider $provider): self
    {
        $url = rtrim((string)$provider->url, '/');

        // DHRU standard endpoint
        if (!str_ends_with($url, '/api/index.php')) {
            $url .= '/api/index.php';
        }

        return new self($url, (string)$provider->username, (string)$provider->api_key, 'JSON');
    }

    public function accountInfo(): array
    {
        return $this->call('accountinfo');
    }

    public function getAllServicesAndGroups(): array
    {
        return $this->call('imeiservicelist');
    }

    public function getFileServices(): array
    {
        return $this->call('fileservicelist');
    }

    public function call(string $action, array $params = []): array
    {
        $payload = [
            'username' => $this->username,
            'apiaccesskey' => $this->apiKey,
            'requestformat' => $this->requestFormat,
            'action' => $action,
        ];

        if (!empty($params)) {
            $payload['requestxml'] = $this->buildRequestXml($params);
        }

        $resp = Http::asForm()
            ->timeout(60)
            ->retry(2, 500)
            ->post($this->endpoint, $payload);

        if (!$resp->successful()) {
            throw new ProviderApiException('dhru', $action, [
                'http_status' => $resp->status(),
                'body' => $resp->body(),
            ], "HTTP {$resp->status()}");
        }

        $body = trim((string)$resp->body());
\Log::info('DHRU RAW RESPONSE', [
    'action' => $action,
    'endpoint' => $this->endpoint,
    'body_first_500' => substr($body, 0, 500),
]);

        // DHRU returns JSON when requestformat=JSON
        $data = json_decode($body, true);

        if (!is_array($data)) {
            // fallback: XML
            $xml = @simplexml_load_string($body);
            if ($xml !== false) {
                $data = json_decode(json_encode($xml), true);
            }
        }

        if (!is_array($data)) {
            throw new ProviderApiException('dhru', $action, ['body' => $body], 'Invalid response');
        }

        // DHRU errors shape
        if (isset($data['ERROR'])) {
            $msg = data_get($data, 'ERROR.0.FULL_DESCRIPTION')
                ?: data_get($data, 'ERROR.0.MESSAGE')
                ?: 'Unknown API error';

            throw new ProviderApiException('dhru', $action, $data, (string)$msg);
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
