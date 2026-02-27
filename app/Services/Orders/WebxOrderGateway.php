<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use App\Services\Api\WebxClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebxOrderGateway
{
    private function client(ApiProvider $p): WebxClient
    {
        return WebxClient::fromProvider($p);
    }

    private function normalizeFields($order): array
    {
        $fields = [];
        if (is_array($order->params ?? null)) {
            $fields = $order->params['fields'] ?? $order->params['required'] ?? [];
        }
        return is_array($fields) ? $fields : [];
    }

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

            if ($name !== '') {
                $slug = Str::snake(Str::of($name)->replaceMatches('/[^\pL\pN]+/u', ' ')->trim()->value());
                if ($slug !== '' && !array_key_exists($slug, $fields)) {
                    $fields[$slug] = $value;
                }
            }

            $nameLc = Str::lower($name);
            if (
                $validation === 'email'
                || str_contains($nameLc, 'email')
                || str_contains(Str::lower($input), 'email')
            ) {
                if (!array_key_exists('email', $fields)) $fields['email'] = $value;
                if (!array_key_exists('EMAIL', $fields)) $fields['EMAIL'] = $value;
            }
        }

        return $fields;
    }

    private function ensureEmailAliasFromValues(array $fields): array
    {
        if (array_key_exists('email', $fields) || array_key_exists('EMAIL', $fields) || array_key_exists('Email', $fields)) {
            return $fields;
        }

        foreach ($fields as $k => $v) {
            $key = strtolower(trim((string)$k));
            $val = trim((string)$v);

            if ($val === '') continue;

            if (str_contains($key, 'email') || filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $fields['email'] = $val;
                $fields['EMAIL'] = $val;
                $fields['Email'] = $val;
                break;
            }
        }

        return $fields;
    }

    private function prepareFields($order): array
    {
        $fields = $this->normalizeFields($order);
        $fields = $this->enrichRequiredFieldAliases($fields, $order->service ?? null);
        $fields = $this->ensureEmailAliasFromValues($fields);

        if (isset($fields['EMAIL']) && !isset($fields['email'])) $fields['email'] = (string)$fields['EMAIL'];
        if (isset($fields['email']) && !isset($fields['Email'])) $fields['Email'] = (string)$fields['email'];
        if (isset($fields['Email']) && !isset($fields['EMAIL'])) $fields['EMAIL'] = (string)$fields['Email'];

        return $fields;
    }

    private function flattenErrors($errors): string
    {
        if (!is_array($errors) || empty($errors)) return 'provider_error';
        $messages = [];
        foreach ($errors as $err) {
            $messages[] = is_array($err) ? implode(', ', $err) : (string)$err;
        }
        return trim(implode(' | ', array_filter($messages)));
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

    private function rejectResult(ApiProvider $p, string $url, string $method, array $params, $raw, int $httpStatus = 0, string $msg = 'Rejected'): array
    {
        return [
            'ok' => false,
            'retryable' => false,
            'status' => 'rejected',
            'remote_id' => null,
            'request' => [
                'url' => $url,
                'method' => $method,
                'params' => $params,
                'http_status' => $httpStatus,
            ],
            'response_raw' => is_array($raw) ? $raw : ['raw' => (string)$raw, 'http_status' => $httpStatus],
            'response_ui' => ['type' => 'error', 'message' => $msg],
        ];
    }

    private function postRaw(ApiProvider $p, string $route, array $params): array
    {
        $c = $this->client($p);
        $url = $c->url($route);

        $params['username'] = (string) $p->username;

        $resp = $c->client()->asForm()->post($url, $params);

        if (!$resp->successful()) {
            $body = (string) $resp->body();
            $snippet = trim(substr($body, 0, 600));
            throw new \RuntimeException('WebX HTTP ' . $resp->status() . ': ' . ($snippet !== '' ? $snippet : 'empty_body'));
        }

        $data = $resp->json();
        if (!is_array($data)) {
            $body = (string) $resp->body();
            $snippet = trim(substr($body, 0, 600));
            throw new \RuntimeException('WebX: Invalid JSON. First bytes: ' . ($snippet !== '' ? $snippet : 'empty_body'));
        }

        if (!empty($data['errors'])) {
            $msg = $this->flattenErrors($data['errors'] ?? []);
            throw new \RuntimeException($msg ?: 'WebX: API error');
        }

        return $data;
    }

    private function getRaw(ApiProvider $p, string $route, array $params = []): array
    {
        $c = $this->client($p);
        $url = $c->url($route);

        $params['username'] = (string) $p->username;

        $resp = $c->client()->get($url, $params);

        if (!$resp->successful()) {
            $body = (string) $resp->body();
            $snippet = trim(substr($body, 0, 600));
            throw new \RuntimeException('WebX HTTP ' . $resp->status() . ': ' . ($snippet !== '' ? $snippet : 'empty_body'));
        }

        $data = $resp->json();
        if (!is_array($data)) {
            $body = (string) $resp->body();
            $snippet = trim(substr($body, 0, 600));
            throw new \RuntimeException('WebX: Invalid JSON. First bytes: ' . ($snippet !== '' ? $snippet : 'empty_body'));
        }

        if (!empty($data['errors'])) {
            $msg = $this->flattenErrors($data['errors'] ?? []);
            throw new \RuntimeException($msg ?: 'WebX: API error');
        }

        return $data;
    }

    /**
     * ✅ FIXED:
     * - supports numeric strings in "status" (e.g. "4" => success, "3" => rejected)
     * - supports HTML in "response" field
     */
    private function normalizeWebxStatus(array $data): array
    {
        $rawStatus = $data['status'] ?? $data['order_status'] ?? $data['state'] ?? $data['result_status'] ?? null;

        if ($rawStatus !== null && is_numeric($rawStatus)) {
            $stInt = (int)$rawStatus;
            if ($stInt === 4) $status = 'success';
            elseif ($stInt === 3) $status = 'rejected';
            else $status = 'inprogress';
        } else {
            $st = strtolower(trim((string)$rawStatus));
            $status = 'inprogress';

            $successTokens = ['success','successful','done','completed','complete','finished','delivered','approved'];
            $rejectTokens  = ['rejected','reject','failed','fail','error','invalid','cancelled','canceled','cancel'];

            if ($st !== '') {
                if (in_array($st, $successTokens, true)) $status = 'success';
                elseif (in_array($st, $rejectTokens, true)) {
                    $status = ($st === 'cancelled' || $st === 'canceled' || $st === 'cancel') ? 'cancelled' : 'rejected';
                }
            }
        }

        $resultText = (string)(
            $data['result_text'] ??
            $data['response'] ??
            $data['result'] ??
            $data['reply'] ??
            $data['message'] ??
            ''
        );

        $items = $data['result_items'] ?? null;

        if ($status === 'inprogress') {
            if (trim($resultText) !== '' || (is_array($items) && !empty($items))) {
                $status = 'success';
            }
        }

        $ui = [
            'type' => ($status === 'success') ? 'success' : (($status === 'rejected') ? 'error' : 'info'),
            'message' => ($status === 'success') ? 'Result available' : (($status === 'rejected') ? 'Rejected' : 'In progress'),
        ];

        if (trim($resultText) !== '') $ui['result_text'] = $resultText;
        if (is_array($items) && !empty($items)) $ui['result_items'] = $items;

        return [$status, $ui];
    }

    // ===== PLACE =====

    public function placeImeiOrder(ApiProvider $p, ImeiOrder $order): array
    {
        $c = $this->client($p);
        $url = $c->url('imei-orders');

        $serviceId = trim((string)($order->service?->remote_id ?? ''));
        $imei = (string)($order->device ?? '');

        $params = array_merge([
            'service_id' => $serviceId,
            'device'     => $imei,
            'comments'   => (string)($order->comments ?? ''),
        ], $this->prepareFields($order));

        try {
            $data = $this->postRaw($p, 'imei-orders', $params);

            $remoteId = (string)($data['id'] ?? $data['order_id'] ?? '');
            if ($remoteId === '' || $remoteId === '0') {
                return $this->errorResult($p, $url, 'POST', $params, $data, 200, 'Temporary provider error, queued.');
            }

            return [
                'ok' => true,
                'retryable' => false,
                'status' => 'inprogress',
                'remote_id' => $remoteId,
                'request' => ['url' => $url, 'method' => 'POST', 'params' => $params, 'http_status' => 200],
                'response_raw' => $data,
                'response_ui' => ['type' => 'success', 'message' => 'Order submitted', 'reference_id' => $remoteId],
            ];
        } catch (\Throwable $e) {
            return $this->errorResult($p, $url, 'POST', $params, ['exception' => $e->getMessage()], 0);
        }
    }

    public function placeServerOrder(ApiProvider $p, ServerOrder $order): array
    {
        $c = $this->client($p);
        $url = $c->url('server-orders');

        $serviceId = trim((string)($order->service?->remote_id ?? ''));
        $qty = (int)($order->quantity ?? 1);
        if ($qty < 1) $qty = 1;

        $params = array_merge([
            'service_id' => $serviceId,
            'quantity'   => $qty,
            'comments'   => (string)($order->comments ?? ''),
        ], $this->prepareFields($order));

        try {
            $data = $this->postRaw($p, 'server-orders', $params);

            $remoteId = (string)($data['id'] ?? $data['order_id'] ?? '');
            if ($remoteId === '' || $remoteId === '0') {
                return $this->errorResult($p, $url, 'POST', $params, $data, 200, 'Temporary provider error, queued.');
            }

            return [
                'ok' => true,
                'retryable' => false,
                'status' => 'inprogress',
                'remote_id' => $remoteId,
                'request' => ['url' => $url, 'method' => 'POST', 'params' => $params, 'http_status' => 200],
                'response_raw' => $data,
                'response_ui' => ['type' => 'success', 'message' => 'Order submitted', 'reference_id' => $remoteId],
            ];
        } catch (\Throwable $e) {
            return $this->errorResult($p, $url, 'POST', $params, ['exception' => $e->getMessage()], 0);
        }
    }

    /**
     * ✅ FINAL FIX for file upload:
     * - Try multiple candidate multipart field names until one works.
     * - If provider returns 4xx validation ("field is required"), try next candidate.
     * - If provider returns other 4xx => reject.
     * - If 5xx/network => waiting.
     */
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

        $c = $this->client($p);
        $url = $c->url('file-orders');

        $params = [
            'service_id' => $serviceId,
            'comments'   => (string)($order->comments ?? ''),
        ];
        $params = array_merge($params, $this->prepareFields($order));

        $preferred = trim((string) data_get($p, 'params.file_field', 'device'));
        if ($preferred === '') $preferred = 'device';

        $candidates = array_values(array_unique(array_filter([
            $preferred,
            'device',
            'Device',
            'file',
            'File',
            'upload',
            'attachment',
        ])));

        $lastStatus = 0;
        $lastBody = '';
        $lastJson = null;

        foreach ($candidates as $fieldName) {
            try {
                $resp = $c->postMultipart(
                    'file-orders',
                    array_merge(['username' => (string)$p->username], $params),
                    $fieldName,
                    $raw,
                    $filename
                );

                $lastStatus = (int)$resp->status();
                $lastBody = (string)$resp->body();
                $lastJson = $resp->json();

                // Success with JSON
                if ($resp->successful() && is_array($lastJson)) {
                    if (!empty($lastJson['errors'])) {
                        $msg = $this->flattenErrors($lastJson['errors']);
                        return $this->rejectResult($p, $url, 'POST', $params + ['_file_field' => $fieldName], $lastJson, $lastStatus, $msg ?: 'Rejected');
                    }

                    $remoteId = (string)($lastJson['id'] ?? $lastJson['order_id'] ?? '');
                    if ($remoteId === '' || $remoteId === '0') {
                        return $this->errorResult($p, $url, 'POST', $params + ['_file_field' => $fieldName], $lastJson, $lastStatus, 'Temporary provider error, queued.');
                    }

                    return [
                        'ok' => true,
                        'retryable' => false,
                        'status' => 'inprogress',
                        'remote_id' => $remoteId,
                        'request' => ['url' => $url, 'method' => 'POST', 'params' => $params + ['_file_field' => $fieldName], 'http_status' => $lastStatus],
                        'response_raw' => $lastJson,
                        'response_ui' => ['type' => 'success', 'message' => 'Order submitted', 'reference_id' => $remoteId],
                    ];
                }

                // 4xx: try next candidate ONLY if it's clearly "field required"
                if ($lastStatus >= 400 && $lastStatus < 500) {
                    $bodyL = strtolower($lastBody);
                    if (str_contains($bodyL, 'field is required') || str_contains($bodyL, 'required')) {
                        continue;
                    }

                    // otherwise reject
                    return $this->rejectResult(
                        $p,
                        $url,
                        'POST',
                        $params + ['_file_field' => $fieldName],
                        is_array($lastJson) ? $lastJson : ['raw' => $lastBody],
                        $lastStatus,
                        'Rejected (file upload)'
                    );
                }

                // 5xx => waiting
                if ($lastStatus >= 500) {
                    return $this->errorResult(
                        $p,
                        $url,
                        'POST',
                        $params + ['_file_field' => $fieldName],
                        is_array($lastJson) ? $lastJson : ['raw' => $lastBody],
                        $lastStatus,
                        'Temporary provider error, queued.'
                    );
                }

            } catch (\Throwable $e) {
                // try next field name
                $lastStatus = 0;
                $lastBody = $e->getMessage();
                $lastJson = ['exception' => $e->getMessage()];
                continue;
            }
        }

        // All candidates failed -> reject with last known message
        $msg = 'Rejected: file field mismatch';
        if (stripos($lastBody, 'Device field is required') !== false) {
            $msg = 'Rejected: provider did not accept file field name';
        }

        return $this->rejectResult(
            $p,
            $url,
            'POST',
            $params + ['_file_field_tried' => $candidates],
            is_array($lastJson) ? $lastJson : ['raw' => $lastBody],
            $lastStatus,
            $msg
        );
    }

    // ===== GET (NORMALIZED) =====

    public function getImeiOrder(ApiProvider $p, string $id): array
    {
        $c = $this->client($p);
        $url = $c->url('imei-orders/' . $id);

        try {
            $data = $this->getRaw($p, 'imei-orders/' . $id);
            [$status, $ui] = $this->normalizeWebxStatus($data);

            return [
                'ok' => true,
                'retryable' => false,
                'status' => $status,
                'remote_id' => $id,
                'request' => ['url' => $url, 'method' => 'GET', 'params' => ['username' => (string)$p->username], 'http_status' => 200],
                'response_raw' => $data,
                'response_ui' => array_merge($ui, ['reference_id' => $id]),
            ];
        } catch (\Throwable $e) {
            return $this->errorResult($p, $url, 'GET', ['username' => (string)$p->username], ['exception' => $e->getMessage()], 0);
        }
    }

    public function getServerOrder(ApiProvider $p, string $id): array
    {
        $c = $this->client($p);
        $url = $c->url('server-orders/' . $id);

        try {
            $data = $this->getRaw($p, 'server-orders/' . $id);
            [$status, $ui] = $this->normalizeWebxStatus($data);

            return [
                'ok' => true,
                'retryable' => false,
                'status' => $status,
                'remote_id' => $id,
                'request' => ['url' => $url, 'method' => 'GET', 'params' => ['username' => (string)$p->username], 'http_status' => 200],
                'response_raw' => $data,
                'response_ui' => array_merge($ui, ['reference_id' => $id]),
            ];
        } catch (\Throwable $e) {
            return $this->errorResult($p, $url, 'GET', ['username' => (string)$p->username], ['exception' => $e->getMessage()], 0);
        }
    }

    public function getFileOrder(ApiProvider $p, string $id): array
    {
        $c = $this->client($p);
        $url = $c->url('file-orders/' . $id);

        try {
            $data = $this->getRaw($p, 'file-orders/' . $id);
            [$status, $ui] = $this->normalizeWebxStatus($data);

            return [
                'ok' => true,
                'retryable' => false,
                'status' => $status,
                'remote_id' => $id,
                'request' => ['url' => $url, 'method' => 'GET', 'params' => ['username' => (string)$p->username], 'http_status' => 200],
                'response_raw' => $data,
                'response_ui' => array_merge($ui, ['reference_id' => $id]),
            ];
        } catch (\Throwable $e) {
            return $this->errorResult($p, $url, 'GET', ['username' => (string)$p->username], ['exception' => $e->getMessage()], 0);
        }
    }
}