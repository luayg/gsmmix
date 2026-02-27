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
        private string $requestFormat = 'JSON',
        private ?ApiProvider $provider = null
    ) {}

    public static function fromProvider(ApiProvider $provider): self
    {
        $url = rtrim((string)$provider->url, '/');

        // DHRU standard endpoint
        if (!str_ends_with($url, '/api/index.php')) {
            $url .= '/api/index.php';
        }

        return new self(
            $url,
            (string)$provider->username,
            (string)$provider->api_key,
            'JSON',
            $provider
        );
    }

    private function httpOptions(): array
    {
        $timeout = (int) data_get($this->provider, 'params.timeout', 10);
        $connect = (int) data_get($this->provider, 'params.connect_timeout', 5);
        $retries = (int) data_get($this->provider, 'params.retries', 0);
        $sleepMs = (int) data_get($this->provider, 'params.retry_sleep_ms', 200);

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

    public function accountInfo(): array
    {
        return $this->call('accountinfo');
    }

    public function getAllServicesAndGroups(): array
    {
        // some installs use imeiservicelist; keep your original behavior
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

        [$timeout, $connect, $retries, $sleepMs] = $this->httpOptions();

        $req = Http::asForm()
            ->connectTimeout($connect)
            ->timeout($timeout);

        if ($retries > 0) {
            $req = $req->retry($retries, $sleepMs);
        }

        $resp = $req->post($this->endpoint, $payload);

        if (!$resp->successful()) {
            throw new ProviderApiException('dhru', $action, [
                'http_status' => $resp->status(),
                'body' => $resp->body(),
            ], "HTTP {$resp->status()}");
        }

        $body = trim((string)$resp->body());

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