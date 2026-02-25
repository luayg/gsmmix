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
        // WebX library uses: $url.'/api/'.$route
        return rtrim((string)$p->url, '/') . '/api';
    }

    private function client(ApiProvider $p): PendingRequest
    {
        // Auth-Key: bcrypt hash of username+key (WebX doc/library)
        $auth = password_hash((string)$p->username . (string)$p->api_key, PASSWORD_BCRYPT);

        return Http::withHeaders([
            'Accept'   => 'application/json',
            'Auth-Key' => $auth,
        ])->timeout(60)->retry(2, 500);
    }

    private function call(ApiProvider $p, string $route, string $method = 'GET', array $params = [], array $attachFile = []): array
    {
        $url = rtrim($this->apiBase($p), '/') . '/' . ltrim($route, '/');

        // Always include username param
        $params['username'] = (string)$p->username;

        $req = $this->client($p);

        if (!empty($attachFile)) {
            // multipart upload
            $req = $req->asMultipart()->attach(
                $attachFile['name'],
                $attachFile['contents'],
                $attachFile['filename'] ?? 'file.bin'
            );
        } else {
            $req = $req->asForm();
        }

        $resp = match (strtoupper($method)) {
            'POST'   => $req->post($url, $params),
            'DELETE' => $req->delete($url, $params),
            default  => $req->get($url, $params),
        };

        $data = $resp->json();
        if (!is_array($data)) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => ['url' => $url, 'method' => $method, 'params' => $params, 'http_status' => $resp->status()],
                'response_raw' => ['raw' => (string)$resp->body()],
                'response_ui' => ['type' => 'queued', 'message' => 'Invalid JSON from WebX, queued.'],
            ];
        }

        if (!empty($data['errors'])) {
            $msg = $this->flattenErrors($data['errors']);
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => ['url' => $url, 'method' => $method, 'params' => $params, 'http_status' => $resp->status()],
                'response_raw' => $data,
                'response_ui' => ['type' => 'queued', 'message' => $msg ?: 'WebX error, queued.'],
            ];
        }

        return [
            'ok' => true,
            'retryable' => false,
            'status' => 'inprogress',
            'remote_id' => (string)($data['id'] ?? ''),
            'request' => ['url' => $url, 'method' => $method, 'params' => $params, 'http_status' => $resp->status()],
            'response_raw' => $data,
            'response_ui' => ['type' => 'success', 'message' => 'Order submitted', 'reference_id' => (string)($data['id'] ?? '')],
        ];
    }

    private function flattenErrors($errors): string
    {
        if (!is_array($errors)) return '';
        $msgs = [];
        foreach ($errors as $e) {
            $msgs[] = is_array($e) ? implode(', ', $e) : (string)$e;
        }
        return trim(implode(', ', array_filter($msgs)));
    }

    /* =========================
     * PLACE
     * ========================= */

    public function placeImeiOrder(ApiProvider $p, ImeiOrder $order): array
    {
        $serviceId = (string)($order->service?->remote_id ?? '');
        $device    = (string)($order->device ?? '');
        $fields    = is_array($order->params) ? ($order->params['fields'] ?? []) : [];

        return $this->call($p, 'imei-orders', 'POST', array_merge([
            'service_id' => $serviceId,
            'device'     => $device,
            'comments'   => (string)($order->comments ?? ''),
        ], is_array($fields) ? $fields : []));
    }

    public function placeServerOrder(ApiProvider $p, ServerOrder $order): array
    {
        $serviceId = (string)($order->service?->remote_id ?? '');
        $qty       = (int)($order->quantity ?? 1);
        if ($qty < 1) $qty = 1;

        $fields = is_array($order->params) ? ($order->params['fields'] ?? []) : [];

        return $this->call($p, 'server-orders', 'POST', array_merge([
            'service_id' => $serviceId,
            'quantity'   => $qty,
            'comments'   => (string)($order->comments ?? ''),
        ], is_array($fields) ? $fields : []));
    }

    public function placeFileOrder(ApiProvider $p, FileOrder $order): array
    {
        $serviceId = (string)($order->service?->remote_id ?? '');
        $path      = (string)($order->storage_path ?? '');

        if ($path === '' || !Storage::exists($path)) {
            return [
                'ok' => false,
                'retryable' => false,
                'status' => 'rejected',
                'remote_id' => null,
                'request' => null,
                'response_raw' => ['errors' => ['file' => ['File not found']]],
                'response_ui' => ['type' => 'error', 'message' => 'File not found on server'],
            ];
        }

        $raw = Storage::get($path);
        $filename = (string)($order->device ?? basename($path));

        $fields = is_array($order->params) ? ($order->params['fields'] ?? []) : [];

        return $this->call(
            $p,
            'file-orders',
            'POST',
            array_merge([
                'service_id' => $serviceId,
                'device'     => $filename,
                'comments'   => (string)($order->comments ?? ''),
            ], is_array($fields) ? $fields : []),
            ['name' => 'device', 'contents' => $raw, 'filename' => $filename] // matches WebX library "device" file upload
        );
    }

    /* =========================
     * GET STATUS
     * ========================= */

    public function getImeiOrder(ApiProvider $p, string $remoteId): array
    {
        return $this->getOrderCommon($p, 'imei-orders/' . $remoteId);
    }

    public function getServerOrder(ApiProvider $p, string $remoteId): array
    {
        return $this->getOrderCommon($p, 'server-orders/' . $remoteId);
    }

    public function getFileOrder(ApiProvider $p, string $remoteId): array
    {
        return $this->getOrderCommon($p, 'file-orders/' . $remoteId);
    }

    private function getOrderCommon(ApiProvider $p, string $route): array
    {
        $url = rtrim($this->apiBase($p), '/') . '/' . ltrim($route, '/');

        $resp = $this->client($p)->get($url, ['username' => (string)$p->username]);
        $data = $resp->json();

        if (!is_array($data) || !empty($data['errors'])) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => ['url' => $url, 'method' => 'GET', 'http_status' => $resp->status()],
                'response_raw' => is_array($data) ? $data : ['raw' => (string)$resp->body()],
                'response_ui' => ['type' => 'queued', 'message' => 'WebX status check failed, queued.'],
            ];
        }

        // WebX library returns numeric status, keep it and map minimal:
        // 0 waiting, 1 in_process, 2 cancelled, 3 rejected, 4 success (from their Order constants)
        $st = (int)($data['status'] ?? 1);

        $mapped = match ($st) {
            4 => 'success',
            3 => 'rejected',
            2 => 'cancelled',
            default => 'inprogress',
        };

        return [
            'ok' => true,
            'retryable' => false,
            'status' => $mapped,
            'remote_id' => (string)($data['id'] ?? ''),
            'request' => ['url' => $url, 'method' => 'GET', 'http_status' => $resp->status()],
            'response_raw' => $data,
            'response_ui' => [
                'type' => $mapped === 'success' ? 'success' : ($mapped === 'rejected' ? 'error' : 'info'),
                'message' => (string)($data['response'] ?? ($mapped === 'success' ? 'Result available' : 'In progress')),
                'reference_id' => (string)($data['id'] ?? ''),
                'webx_status' => $st,
            ],
        ];
    }
}