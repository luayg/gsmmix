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

    private function firstNotEmpty(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normalizeLines($value): string
    {
        if (is_array($value)) {
            $lines = [];
            foreach ($value as $item) {
                $item = trim((string)$item);
                if ($item !== '') {
                    $lines[] = $item;
                }
            }
            return implode("\n", $lines);
        }

        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        $parts = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $parts = array_values(array_filter(array_map(
            static fn ($v) => trim((string)$v),
            $parts
        ), static fn ($v) => $v !== ''));

        return implode("\n", $parts);
    }

    private function intOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)$value : null;
    }

    private function setTextIfPresent(array &$payload, string $key, $value): void
    {
        $value = trim((string)$value);
        if ($value !== '') {
            $payload[$key] = $value;
        }
    }

    private function setIntIfPresent(array &$payload, string $key, $value): void
    {
        $value = $this->intOrNull($value);
        if ($value !== null) {
            $payload[$key] = $value;
        }
    }

    private function setLinesIfPresent(array &$payload, string $key, $value): void
    {
        $value = $this->normalizeLines($value);
        if ($value !== '') {
            $payload[$key] = $value;
        }
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

        // subscriptions
        if ($type === 'subscriptions') {
            $this->setTextIfPresent($payload, 'username', $fields['username'] ?? null);
            $this->setIntIfPresent($payload, 'min', $fields['min'] ?? null);
            $this->setIntIfPresent($payload, 'max', $fields['max'] ?? null);
            $this->setIntIfPresent($payload, 'posts', $fields['posts'] ?? null);
            $this->setIntIfPresent($payload, 'old_posts', $fields['old_posts'] ?? null);
            $this->setIntIfPresent($payload, 'delay', $fields['delay'] ?? null);
            $this->setTextIfPresent($payload, 'expiry', $fields['expiry'] ?? null);

            return $payload;
        }

        $target = $this->firstNotEmpty([
            $fields['link'] ?? '',
            $order->device ?? '',
        ]);

        if ($target !== '') {
            $payload['link'] = $target;
        }

        // quantity
        $quantity = $this->intOrNull($fields['quantity'] ?? null);
        if ($quantity === null && !empty($order->quantity)) {
            $quantity = (int)$order->quantity;
        }

        switch ($type) {
            case 'default':
                if ($quantity !== null) {
                    $payload['quantity'] = $quantity;
                }
                break;

            case 'package':
                // عادة package يحتاج link فقط
                break;

            case 'drip-feed':
                if ($quantity !== null) {
                    $payload['quantity'] = $quantity;
                }
                $this->setIntIfPresent($payload, 'runs', $fields['runs'] ?? null);
                $this->setIntIfPresent($payload, 'interval', $fields['interval'] ?? null);
                break;

            case 'mentions user followers':
                $this->setTextIfPresent($payload, 'username', $fields['username'] ?? null);
                if ($quantity !== null) {
                    $payload['quantity'] = $quantity;
                }
                break;

            case 'comment likes':
                $this->setTextIfPresent($payload, 'username', $fields['username'] ?? null);
                if ($quantity !== null) {
                    $payload['quantity'] = $quantity;
                }
                break;

            case 'mentions custom list':
                $this->setLinesIfPresent($payload, 'usernames', $fields['usernames'] ?? null);
                break;

            case 'poll':
                if ($quantity !== null) {
                    $payload['quantity'] = $quantity;
                }
                $this->setIntIfPresent($payload, 'answer_number', $fields['answer_number'] ?? null);
                break;

            case 'custom comments':
                $this->setLinesIfPresent($payload, 'comments', $fields['comments'] ?? null);
                break;

            default:
                // fallback مرن للأنواع غير المعروفة
                if ($quantity !== null) {
                    $payload['quantity'] = $quantity;
                }
                $this->setTextIfPresent($payload, 'username', $fields['username'] ?? null);
                $this->setLinesIfPresent($payload, 'usernames', $fields['usernames'] ?? null);
                $this->setLinesIfPresent($payload, 'comments', $fields['comments'] ?? null);
                $this->setIntIfPresent($payload, 'answer_number', $fields['answer_number'] ?? null);
                $this->setIntIfPresent($payload, 'runs', $fields['runs'] ?? null);
                $this->setIntIfPresent($payload, 'interval', $fields['interval'] ?? null);
                break;
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

        try {
            $response = Http::asForm()
                ->timeout(60)
                ->retry(1, 500)
                ->post($url, $payload);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => [
                    'url' => $url,
                    'method' => 'POST',
                    'params' => $payload,
                    'http_status' => 0,
                ],
                'response_raw' => [
                    'raw' => $e->getMessage(),
                    'http_status' => 0,
                    'exception' => $e->getMessage(),
                ],
                'response_ui' => [
                    'type' => 'error',
                    'message' => $e->getMessage(),
                    'result_text' => $e->getMessage(),
                ],
            ];
        }

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

        if (!empty($json['error'])) {
            $message = trim((string)$json['error']);

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
                    'raw' => $json,
                    'http_status' => $status,
                ],
                'response_ui' => [
                    'type' => 'error',
                    'message' => $message !== '' ? $message : 'Provider error',
                    'result_text' => json_encode($json, JSON_UNESCAPED_UNICODE),
                ],
            ];
        }

        $remoteId = $this->firstNotEmpty([
            $json['order'] ?? '',
            $json['order_id'] ?? '',
            data_get($json, 'data.order', ''),
            data_get($json, 'data.order_id', ''),
            data_get($json, 'id', ''),
        ]);

        if ($remoteId === '') {
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
                    'raw' => $json,
                    'http_status' => $status,
                ],
                'response_ui' => [
                    'type' => 'error',
                    'message' => 'Provider response missing order id',
                    'result_text' => json_encode($json, JSON_UNESCAPED_UNICODE),
                ],
            ];
        }

        return [
            'ok' => true,
            'retryable' => false,
            'status' => 'inprogress',
            'remote_id' => $remoteId,
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
                'message' => 'ORDER SENT',
                'result_text' => 'Order #' . $remoteId,
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