<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebxOrderGateway
{
    private function apiBase(ApiProvider $p): string
    {
        return rtrim((string)$p->url, '/') . '/api';
    }

    private function client(ApiProvider $p): PendingRequest
    {
        $auth = password_hash((string)$p->username . (string)$p->api_key, PASSWORD_BCRYPT);

        return Http::withHeaders([
            'Accept'   => 'application/json',
            'Auth-Key' => $auth,
        ])->timeout(60)->retry(2, 500);
    }

    private function normalizeFields($order): array
    {
        $fields = [];
        if (is_array($order->params ?? null)) {
            $fields = $order->params['fields'] ?? $order->params['required'] ?? [];
        }
        return is_array($fields) ? $fields : [];
    }

    /**
     * ✅ أهم خطوة: توليد aliases مثل DHRU:
     * - يحوّل service_fields_1 إلى email إذا كان تعريف الحقل Email أو validation=email
     * - ويضيف snake_case من اسم الحقل
     */
    private function enrichRequiredFieldAliases(array $fields, $service): array
    {
        if (empty($fields) || !$service) return $fields;

        $params = $service->params ?? [];
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            $params = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($params)) return $fields;

        $customFields = $params['custom_fields'] ?? [];
        if (!is_array($customFields) || empty($customFields)) return $fields;

        foreach ($customFields as $def) {
            if (!is_array($def)) continue;

            $input = trim((string)($def['input'] ?? ''));
            if ($input === '' || !array_key_exists($input, $fields)) continue;

            $value = (string)$fields[$input];
            $name = trim((string)($def['name'] ?? ''));
            $validation = Str::lower(trim((string)($def['validation'] ?? '')));

            // alias باسم الحقل (snake_case)
            if ($name !== '') {
                $slug = Str::snake(Str::of($name)->replaceMatches('/[^\pL\pN]+/u', ' ')->trim()->value());
                if ($slug !== '' && !array_key_exists($slug, $fields)) {
                    $fields[$slug] = $value;
                }
            }

            // alias email
            $nameLc = Str::lower($name);
            if (
                $validation === 'email'
                || str_contains($nameLc, 'email')
                || str_contains(Str::lower($input), 'email')
            ) {
                if (!array_key_exists('email', $fields)) {
                    $fields['email'] = $value;
                }
                if (!array_key_exists('EMAIL', $fields)) {
                    $fields['EMAIL'] = $value;
                }
            }
        }

        return $fields;
    }

    /**
     * ✅ fallback: لو ما عندنا custom_fields، اكتشف الإيميل من القيمة نفسها
     */
    private function ensureEmailAliasFromValues(array $fields): array
    {
        if (array_key_exists('email', $fields) || array_key_exists('EMAIL', $fields)) {
            return $fields;
        }

        foreach ($fields as $k => $v) {
            $key = strtolower(trim((string)$k));
            $val = trim((string)$v);

            if ($val === '') continue;

            if (str_contains($key, 'email') || filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $fields['email'] = $val;
                $fields['EMAIL'] = $val;
                break;
            }
        }

        return $fields;
    }

    /**
     * ✅ طبّق aliases على fields قبل الإرسال
     */
    private function prepareFields($order): array
    {
        $fields = $this->normalizeFields($order);
        $fields = $this->enrichRequiredFieldAliases($fields, $order->service ?? null);
        $fields = $this->ensureEmailAliasFromValues($fields);

        // بعض APIs تتطلب lowercase فقط، فنضمن email lowercase موجود
        if (isset($fields['EMAIL']) && !isset($fields['email'])) {
            $fields['email'] = (string)$fields['EMAIL'];
        }

        return $fields;
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

    private function flattenErrors($errors): string
    {
        if (!is_array($errors) || empty($errors)) return 'could_not_connect_to_api';
        $messages = [];
        foreach ($errors as $err) {
            $messages[] = is_array($err) ? implode(', ', $err) : (string)$err;
        }
        return trim(implode(', ', array_filter($messages)));
    }

    private function post(ApiProvider $p, string $route, array $params): array
    {
        $url = rtrim($this->apiBase($p), '/') . '/' . ltrim($route, '/');

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

    // ===== PLACE =====

    public function placeImeiOrder(ApiProvider $p, ImeiOrder $order): array
    {
        $serviceId = trim((string)($order->service?->remote_id ?? ''));
        $imei = (string)($order->device ?? '');

        $fields = $this->prepareFields($order);

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

        $fields = $this->prepareFields($order);

        return $this->post($p, 'server-orders', array_merge([
            'service_id' => $serviceId,
            'quantity'   => $qty,
            'comments'   => (string)($order->comments ?? ''),
        ], $fields));
    }

    public function placeFileOrder(ApiProvider $p, FileOrder $order): array
    {
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

        $fields = $this->prepareFields($order);
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