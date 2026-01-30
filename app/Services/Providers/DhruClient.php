<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;

class DhruClient
{
    public function __construct(protected ApiProvider $provider) {}

    /**
     * DHRU Fusion V6.x common request
     * POST:
     * - username
     * - apiaccesskey
     * - action
     * - requestformat=json
     * - parameters=base64_encode(json_encode($parameters))
     */
    public function call(string $action, array $parameters = []): array
    {
        $url = rtrim((string)$this->provider->url, '/').'/';

        $post = [
            'username'      => (string)$this->provider->username,
            'apiaccesskey'  => (string)$this->provider->api_key,
            'action'        => $action,
            'requestformat' => 'json',
            'parameters'    => base64_encode(json_encode($parameters, JSON_UNESCAPED_UNICODE)),
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($post),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $raw === null) {
            return ['ok' => false, 'error' => 'cURL error: '.$err, 'http' => $code];
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'Invalid JSON from provider', 'http' => $code, 'raw' => $raw];
        }

        return ['ok' => true, 'http' => $code, 'data' => $json, 'raw' => $raw];
    }

    public function placeOrder(int $serviceRemoteId, array $payload): array
    {
        // DHRU commonly uses placeimeiorder for all service types (IMEI/SERVER),
        // and file sometimes uses "placefileorder" depending on supplier implementation.
        // We'll start with placeimeiorder and adjust if needed.
        $params = array_merge(['ID' => $serviceRemoteId], $payload);
        return $this->call('placeimeiorder', $params);
    }

    public function getOrder(int $remoteOrderId): array
    {
        return $this->call('getimeiorder', ['ID' => $remoteOrderId]);
    }
}
