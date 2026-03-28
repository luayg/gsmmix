<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\SmmOrder;
use Illuminate\Support\Facades\Http;

class SmmOrderGateway
{
    private function decodeArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function providerUrl(ApiProvider $provider): string
    {
        return rtrim(trim((string)($provider->url ?? '')), '/');
    }

    private function providerKey(ApiProvider $provider): string
    {
        return trim((string)($provider->api_key ?? ''));
    }

    private function orderFields(SmmOrder $order): array
    {
        $params = $this->decodeArray($order->params ?? []);
        $fields = $params['fields'] ?? $params['required'] ?? [];

        return is_array($fields) ? $fields : [];
    }

    private function serviceParams($service): array
    {
        return $this->decodeArray($service->params ?? []);
    }

    private function smmType($service): string
    {
        $params = $this->serviceParams($service);
        return strtolower(trim((string)($params['smm_type'] ?? 'default')));
    }

    private function remoteServiceId($service): string
    {
        return trim((string)($service->remote_id ?? ''));
    }

    private function buildAddPayload(ApiProvider $provider, SmmOrder $order): array
    {
        $service = $order->service;
        $fields = $this->orderFields($order);
        $type = $this->smmType($service);

        $payload = [
            'key' => $this->providerKey($provider),
            'action' => 'add',
            'service' => $this->remoteServiceId($service),
        ];

        if ($type === 'subscriptions') {
            if (!empty($fields['username'])) $payload['username'] = (string)$fields['username'];
            if (isset($fields['min']) && $fields['min'] !== '') $payload['min'] = $fields['min'];
            if (isset($fields['max']) && $fields['max'] !== '') $payload['max'] = $fields['max'];
            if (isset($fields['posts']) && $fields['posts'] !== '') $payload['posts'] = $fields['posts'];
            if (isset($fields['delay']) && $fields['delay'] !== '') $payload['delay'] = $fields['delay'];
            if (!empty($fields['expiry'])) $payload['expiry'] = (string)$fields['expiry'];
            return $payload;
        }

        $target = trim((string)($fields['link'] ?? $order->device ?? ''));
        if ($target !== '') {
            if ($type === 'comment likes' && !empty($fields['username'])) {
                $payload['link'] = $target;
                $payload['username'] = (string)$fields['username'];
            } else {
                $payload['link'] = $target;
            }
        }

        if ($type === 'custom comments') {
            if (!empty($fields['comments'])) {
                $payload['comments'] = is_array($fields['comments'])
                    ? implode("\n", $fields['comments'])
                    : (string)$fields['comments'];
            }
            return $payload;
        }

        if (isset($fields['quantity']) && $fields['quantity'] !== '') {
            $payload['quantity'] = (int)$fields['quantity'];
        } elseif (!empty($order->quantity)) {
            $payload['quantity'] = (int)$order->quantity;
        }

        if ($type === 'drip-feed') {
            if (isset($fields['runs']) && $fields['runs'] !== '') {
                $payload['runs'] = (int)$fields['runs'];
            }
            if (isset($fields['interval']) && $fields['interval'] !== '') {
                $payload['interval'] = (int)$fields['interval'];
            }
        }

        return $payload;
    }

    private function request(ApiProvider $provider, array $payload): array
    {
        $url = $this->providerUrl($provider);
        if ($url === '') {
            throw new \RuntimeException('INVALID URL');
        }

        if (trim((string)($payload['key'] ?? '')) === '') {
            throw new \RuntimeException('AUTH FAILED');
        }

        $response = Http::asForm()
            ->timeout(60)
            ->retry(1, 500)
            ->post($url, $payload);

        $status = (int)$response->status();
        $body = (string)$response->body();
        $json = $response->json();

        if (!$response->successful()) {
            return [
                'ok' => false,
                'retryable' => $status >= 500 || $status === 0,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => [
                    'url' => $url,
                    'method' => 'POST',
                    'params' => $payload,
                    'http_status' => $status,
                ],
                'response_raw' => [
                    'raw' => $body,
                    'http_status' => $status,
                ],
                'response_ui' => [
                    'type' => 'error',
                    'message' => trim($body) !== '' ? trim($body) : ('HTTP ' . $status),
                    'result_text' => $body,
                ],
            ];
        }

        if (!is_array($json)) {
            return [
                'ok' => false,
                'retryable' => false,
                'status' => 'rejected',
                'remote_id' => null,
                'request' => [
                    'url' => $url,
                    'method' => 'POST',
                    'params' => $payload,
                    'http_status' => $status,
                ],
                'response_raw' => [
                    'raw' => $body,
                    'http_status' => $status,
                ],
                'response_ui' => [
                    'type' => 'error',
                    'message' => trim($body) !== '' ? trim($body) : 'Invalid JSON response',
                    'result_text' => $body,
                ],
            ];
        }

        return [
            'ok' => true,
            'retryable' => false,
            'status' => 'inprogress',
            'remote_id' => (string)($json['order'] ?? ''),
            'request' => [
                'url' => $url,
                'method' => 'POST',
                'params' => $payload,
                'http_status' => $status,
            ],
            'response_raw' => [
                'raw' => $json,
                'http_status' => $status,
            ],
            'response_ui' => [
                'type' => 'success',
                'message' => !empty($json['order']) ? 'ORDER SENT' : 'OK',
                'result_text' => !empty($json['order']) ? ('Order #' . $json['order']) : json_encode($json, JSON_UNESCAPED_UNICODE),
            ],
        ];
    }

    public function placeSmmOrder(ApiProvider $provider, SmmOrder $order): array
    {
        $order->loadMissing('service');

        if (!$order->service) {
            return [
                'ok' => false,
                'retryable' => false,
                'status' => 'rejected',
                'remote_id' => null,
                'request' => [
                    'url' => $this->providerUrl($provider),
                    'method' => 'POST',
                    'params' => [],
                    'http_status' => 0,
                ],
                'response_raw' => [
                    'raw' => 'SERVICE NOT FOUND',
                    'http_status' => 0,
                ],
                'response_ui' => [
                    'type' => 'error',
                    'message' => 'SERVICE NOT FOUND',
                ],
            ];
        }

        $payload = $this->buildAddPayload($provider, $order);

        if (trim((string)($payload['service'] ?? '')) === '') {
            return [
                'ok' => false,
                'retryable' => false,
                'status' => 'rejected',
                'remote_id' => null,
                'request' => [
                    'url' => $this->providerUrl($provider),
                    'method' => 'POST',
                    'params' => $payload,
                    'http_status' => 0,
                ],
                'response_raw' => [
                    'raw' => 'INVALID / DISABLED SERVICE',
                    'http_status' => 0,
                ],
                'response_ui' => [
                    'type' => 'error',
                    'message' => 'INVALID / DISABLED SERVICE',
                ],
            ];
        }

        return $this->request($provider, $payload);
    }

    public function getSmmOrderStatus(ApiProvider $provider, string $remoteId): array
    {
        $payload = [
            'key' => $this->providerKey($provider),
            'action' => 'status',
            'order' => $remoteId,
        ];

        $url = $this->providerUrl($provider);
        if ($url === '') {
            throw new \RuntimeException('INVALID URL');
        }

        if (trim((string)$payload['key']) === '') {
            throw new \RuntimeException('AUTH FAILED');
        }

        $response = Http::asForm()
            ->timeout(60)
            ->retry(1, 500)
            ->post($url, $payload);

        $statusCode = (int)$response->status();
        $body = (string)$response->body();
        $json = $response->json();

        if (!$response->successful()) {
            throw new \RuntimeException($body !== '' ? $body : ('HTTP ' . $statusCode));
        }

        if (!is_array($json)) {
            throw new \RuntimeException($body !== '' ? $body : 'Invalid JSON response');
        }

        if (!empty($json['error'])) {
            return [
                'ok' => false,
                'status' => 'waiting',
                'request' => [
                    'url' => $url,
                    'method' => 'POST',
                    'params' => $payload,
                    'http_status' => $statusCode,
                ],
                'response_raw' => [
                    'raw' => $json,
                    'http_status' => $statusCode,
                ],
                'response_ui' => [
                    'type' => 'error',
                    'message' => (string)$json['error'],
                    'result_text' => json_encode($json, JSON_UNESCAPED_UNICODE),
                ],
            ];
        }

        $providerStatus = strtolower(trim((string)($json['status'] ?? 'in progress')));

        $localStatus = match ($providerStatus) {
            'completed', 'complete', 'success', 'partial' => 'success',
            'canceled', 'cancelled' => 'cancelled',
            'failed', 'fail', 'rejected', 'error' => 'rejected',
            'processing', 'in progress', 'inprogress', 'pending' => 'inprogress',
            default => 'inprogress',
        };

        return [
            'ok' => true,
            'status' => $localStatus,
            'provider_status' => $providerStatus,
            'request' => [
                'url' => $url,
                'method' => 'POST',
                'params' => $payload,
                'http_status' => $statusCode,
            ],
            'response_raw' => [
                'raw' => $json,
                'http_status' => $statusCode,
            ],
            'response_ui' => [
                'type' => $localStatus === 'success' ? 'success' : ($localStatus === 'inprogress' ? 'info' : 'error'),
                'message' => (string)($json['status'] ?? strtoupper($localStatus)),
                'result_text' => json_encode($json, JSON_UNESCAPED_UNICODE),
            ],
        ];
    }
}