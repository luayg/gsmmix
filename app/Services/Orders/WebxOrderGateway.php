<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WebxOrderGateway
{
    private function apiBase(ApiProvider $p): string
    {
        // WebX doc/library: $url . '/api/' . route
        return rtrim((string)$p->url, '/') . '/api';
    }

    private function client(ApiProvider $p): PendingRequest
    {
        // WebX doc/library: Auth-Key = bcrypt(username + key)
        $auth = password_hash((string)$p->username . (string)$p->api_key, PASSWORD_BCRYPT);

        return Http::withHeaders([
            'Accept'   => 'application/json',
            'Auth-Key' => $auth,
        ])->timeout(60)->retry(2, 500);
    }

    private function normalizeFields($order): array
    {
        // نفس فكرة DhruOrderGateway: fields من params['fields'] أو params['required']
        // (ImeiOrder/ServerOrder/FileOrder عندك params cast array) :contentReference[oaicite:4]{index=4}
        $fields = [];
        if (is_array($order->params ?? null)) {
            $fields = $order->params['fields'] ?? $order->params['required'] ?? [];
        }
        return is_array($fields) ? $fields : [];
    }

    private function errorResult(ApiProvider $p, string $url, string $method, array $params, $raw, int $httpStatus = 0, string $msg = 'Temporary provider error, queued.'): array
    {
        return [
            'ok' => false,
            'retryable' => true,
            'status' => 'waiting',
            'remote_id' => null,
            'request' => [
                'url' => $url,
                'method' => $method,
                'params' => $params,
                'http_status' => $httpStatus,
            ],
            'response_raw' => is_array($raw) ? $raw : ['raw' => (string)$raw, 'http_status' => $httpStatus],
            'response_ui' => ['type' => 'queued', 'message' => $msg],
        ];
    }

    private function post(ApiProvider $p, string $route, array $params): array
    {
        $url = rtrim($this->apiBase($p), '/') . '/' . ltrim($route, '/');

        // WebX requires username param
        $params['username'] = (string)$p->username;

        $resp = $this->client($p)->asForm()->post($url, $params);

        $data = $resp->json();
        if (!$resp->successful() || !is_array($data)) {
            return $this->errorResult($p, $url, 'POST', $params, $resp->body(), $resp->status());
        }

        if (!empty($data['errors'])) {
            return $this->errorResult($p, $url, 'POST', $params, $data, $resp->status(), $this->flattenErrors($data['errors']));
        }

        $remoteId = (string)($data['id'] ?? '');
        if ($remoteId === '' || $remoteId === '0') {
            // لم يرجع رقم طلب => نخليها retryable (queued)
            return $this->errorResult($p, $url, 'POST', $params, $data, $resp->status(), 'WebX: missing order id, queued.');
        }

        return [
            'ok' => true,
            'retryable' => false,
            'status' => 'inprogress',
            'remote_id' => $remoteId,
            'request' => [
                'url' => $url,
                'method' => 'POST',
                'params' => $params,
                'http_status' => $resp->status(),
            ],
            'response_raw' => $data,
            'response_ui' => [
                'type' => 'success',
                'message' => 'Order submitted',
                'reference_id' => $remoteId,
            ],
        ];
    }

    private function get(ApiProvider $p, string $route, array $params = []): array
    {
        $url = rtrim($this->apiBase($p), '/') . '/' . ltrim($route, '/');

        $params['username'] = (string)$p->username;

        $resp = $this->client($p)->get($url, $params);
        $data = $resp->json();

        if (!$resp->successful() || !is_array($data)) {
            return $this->errorResult($p, $url, 'GET', $params, $resp->body(), $resp->status());
        }

        if (!empty($data['errors'])) {
            return $this->errorResult($p, $url, 'GET', $params, $data, $resp->status(), $this->flattenErrors($data['errors']));
        }

        // WebX library returns: id, status (0..4), response
        $st = (int)($data['status'] ?? 1);
        $mapped = match ($st) {
            4 => 'success',
            3 => 'rejected',
            2 => 'cancelled',
            0 => 'waiting',
            default => 'inprogress',
        };

        return [
            'ok' => true,
            'retryable' => false,
            'status' => $mapped,
            'remote_id' => (string)($data['id'] ?? ''),
            'request' => [
                'url' => $url,
                'method' => 'GET',
                'params' => $params,
                'http_status' => $resp->status(),
            ],
            'response_raw' => $data,
            'response_ui' => [
                'type' => $mapped === 'success' ? 'success' : ($mapped === 'rejected' ? 'error' : 'info'),
                'message' => (string)($data['response'] ?? ($mapped === 'success' ? 'Result available' : 'In progress')),
                'reference_id' => (string)($data['id'] ?? ''),
                'webx_status' => $st,
                'result_text' => (string)($data['response'] ?? ''),
            ],
        ];
    }

    private function flattenErrors($errors): string
    {
        if (!is_array($errors) || empty($errors)) return 'could_not_connect_to_api';
        $messages = [];
        foreach ($errors as $err) {
            $messages[] = is_array($err) ? implode(', ', $err) : (string)$err;
        }
        return trim(implode(', ', array_filter($messages)));
    }

    // ===== PLACE =====

    public function placeImeiOrder(ApiProvider $p, ImeiOrder $order): array
    {
        $serviceId = trim((string)($order->service?->remote_id ?? ''));
        $imei = (string)($order->device ?? '');

        $fields = $this->normalizeFields($order);

        return $this->post($p, 'imei-orders', array_merge([
            'service_id' => $serviceId,
            'device'     => $imei,
            'comments'   => (string)($order->comments ?? ''),
        ], $fields));
    }

    public function placeServerOrder(ApiProvider $p, ServerOrder $order): array
    {
        $serviceId = trim((string)($order->service?->remote_id ?? ''));
        $qty = (int)($order->quantity ?? 1);
        if ($qty < 1) $qty = 1;

        $fields = $this->normalizeFields($order);

        return $this->post($p, 'server-orders', array_merge([
            'service_id' => $serviceId,
            'quantity'   => $qty,
            'comments'   => (string)($order->comments ?? ''),
        ], $fields));
    }

    public function placeFileOrder(ApiProvider $p, FileOrder $order): array
    {
        // WebX library uses device as CURLFile. عندنا storage_path
        $serviceId = trim((string)($order->service?->remote_id ?? ''));
        $path = (string)($order->storage_path ?? '');
        $filename = (string)($order->device ?? '');

        if ($path === '' || !Storage::exists($path)) {
            return [
                'ok' => false,
                'retryable' => false,
                'status' => 'rejected',
                'remote_id' => null,
                'request' => ['storage_path' => $path],
                'response_raw' => ['error' => 'file_not_found'],
                'response_ui' => ['type' => 'error', 'message' => 'File not found on server'],
            ];
        }

        $raw = Storage::get($path);
        if ($filename === '') $filename = basename($path);

        $url = rtrim($this->apiBase($p), '/') . '/file-orders';

        $params = [
            'username'   => (string)$p->username,
            'service_id' => $serviceId,
            'comments'   => (string)($order->comments ?? ''),
        ];

        $fields = $this->normalizeFields($order);
        $params = array_merge($params, $fields);

        $resp = $this->client($p)
            ->asMultipart()
            ->attach('device', $raw, $filename)
            ->post($url, $params);

        $data = $resp->json();
        if (!$resp->successful() || !is_array($data)) {
            return $this->errorResult($p, $url, 'POST', $params, $resp->body(), $resp->status());
        }
        if (!empty($data['errors'])) {
            return $this->errorResult($p, $url, 'POST', $params, $data, $resp->status(), $this->flattenErrors($data['errors']));
        }

        $remoteId = (string)($data['id'] ?? '');
        if ($remoteId === '' || $remoteId === '0') {
            return $this->errorResult($p, $url, 'POST', $params, $data, $resp->status(), 'WebX: missing order id, queued.');
        }

        return [
            'ok' => true,
            'retryable' => false,
            'status' => 'inprogress',
            'remote_id' => $remoteId,
            'request' => ['url' => $url, 'method' => 'POST', 'params' => $params, 'http_status' => $resp->status()],
            'response_raw' => $data,
            'response_ui' => ['type' => 'success', 'message' => 'Order submitted', 'reference_id' => $remoteId],
        ];
    }

    // ===== GET =====

    public function getImeiOrder(ApiProvider $p, string $id): array
    {
        return $this->get($p, 'imei-orders/' . $id);
    }

    public function getServerOrder(ApiProvider $p, string $id): array
    {
        return $this->get($p, 'server-orders/' . $id);
    }

    public function getFileOrder(ApiProvider $p, string $id): array
    {
        return $this->get($p, 'file-orders/' . $id);
    }
}