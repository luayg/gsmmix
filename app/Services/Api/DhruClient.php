<?php

namespace App\Services\Api;

use App\Exceptions\ProviderApiException;
use App\Models\ApiProvider;
use Illuminate\Support\Facades\Http;

class DhruClient
{
    private string $endpoint;
    private string $username;
    private string $apiKey;
    private string $requestFormat;

    public function __construct(string $endpoint, string $username, string $apiKey, string $requestFormat = 'JSON')
    {
        $this->endpoint = $endpoint;
        $this->username = $username;
        $this->apiKey = $apiKey;
        $this->requestFormat = $requestFormat;
    }

    public static function fromProvider(ApiProvider $provider): self
    {
        return new self(
            $provider->dhruEndpoint(),
            (string) $provider->username,
            (string) $provider->api_key,
            'JSON'
        );
    }

    public function accountInfo(): array
    {
        return $this->call('accountinfo');
    }

    /**
     * DHRU: Get All Services and Groups (includes IMEI + SERVER + REMOTE)
     */
    public function getAllServicesAndGroups(): array
    {
        return $this->call('imeiservicelist');
    }

    /**
     * DHRU: File services list
     */
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
            ->timeout(45)
            ->retry(2, 500)
            ->post($this->endpoint, $payload);

        if (!$resp->successful()) {
            throw new ProviderApiException('dhru', $action, [
                'http_status' => $resp->status(),
                'body' => $resp->body(),
            ], "HTTP error when calling provider API: {$resp->status()}");
        }

        $body = trim((string) $resp->body());
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            // fallback XML
            $xml = @simplexml_load_string($body);
            if ($xml !== false) {
                $json = json_encode($xml);
                $data = json_decode((string)$json, true);
            }
        }

        if (!is_array($data)) {
            throw new ProviderApiException('dhru', $action, [
                'body' => $body,
            ], 'Invalid response (not JSON/XML array)');
        }

        if (isset($data['ERROR'])) {
            $msg = $this->extractErrorMessage($data);
            throw new ProviderApiException('dhru', $action, $data, $msg);
        }

        return $data;
    }

    private function extractErrorMessage(array $data): string
    {
        $message = data_get($data, 'ERROR.0.MESSAGE');
        $full = data_get($data, 'ERROR.0.FULL_DESCRIPTION');

        $message = is_string($message) ? trim($message) : '';
        $full = is_string($full) ? trim($full) : '';

        return $full !== '' ? $full : ($message !== '' ? $message : 'Unknown API error');
    }

    private function buildRequestXml(array $params): string
    {
        $xml = new \SimpleXMLElement('<PARAMETERS/>');

        foreach ($params as $key => $value) {
            $p = $xml->addChild('PARAMETER');
            $p->addChild('KEY', htmlspecialchars((string)$key));
            $p->addChild('VALUE', htmlspecialchars((string)$value));
        }

        return $xml->asXML() ?: '';
    }
}
