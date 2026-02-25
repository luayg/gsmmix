<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GsmhubOrderGateway
{
    private function baseUrl(ApiProvider $p): string
    {
        $base = rtrim((string)$p->url, '/');

        // imei.us requirement: base should be https://imei.us/public
        if (!Str::endsWith($base, '/public')) {
            $base .= '/public';
        }

        return $base;
    }

    private function endpoint(ApiProvider $p): string
    {
        $params = $p->params ?? null;
        if (is_string($params) && trim($params) !== '') {
            $decoded = json_decode($params, true);
            if (is_array($decoded)) $params = $decoded;
        }

        $base = $this->baseUrl($p);

        if (is_array($params) && !empty($params['endpoint'])) {
            $ep = trim((string)$params['endpoint']);
            if ($ep !== '') {
                if (Str::startsWith($ep, ['http://', 'https://'])) return $ep;
                return rtrim($base, '/') . '/' . ltrim($ep, '/');
            }
        }

        return $base . '/api.php';
    }

    private function call(ApiProvider $p, string $action, array $params = []): array
    {
        $payload = [
            'username'      => (string)$p->username,
            'apiaccesskey'  => (string)$p->api_key,
            'requestformat' => 'JSON',
            'action'        => $action,
        ];

        if (!empty($params)) {
            $payload['requestxml'] = $this->buildRequestXml($params);
        }

        $url = $this->endpoint($p);

        $resp = Http::asForm()
            ->timeout(60)
            ->retry(2, 500)
            ->post($url, $payload);

        $body = (string)$resp->body();

        if (!$resp->successful()) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => ['url' => $url, 'action' => $action, 'payload' => $payload, 'http_status' => $resp->status()],
                'response_raw' => ['raw' => $body, 'http_status' => $resp->status()],
                'response_ui' => ['type' => 'queued', 'message' => "HTTP {$resp->status()}, queued."],
            ];
        }

        $data = json_decode($body, true);

        // fallback try XML
        if (!is_array($data)) {
            $xml = @simplexml_load_string($body);
            if ($xml !== false) {
                $data = json_decode(json_encode($xml), true);
            }
        }

        if (!is_array($data)) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => ['url' => $url, 'action' => $action, 'payload' => $payload, 'http_status' => $resp->status()],
                'response_raw' => ['raw' => $body],
                'response_ui' => ['type' => 'queued', 'message' => 'Invalid response, queued.'],
            ];
        }

        if (isset($data['ERROR'])) {
            $msg =
                data_get($data, 'ERROR.0.FULL_DESCRIPTION')
                ?: data_get($data, 'ERROR.0.MESSAGE')
                ?: 'Unknown provider error';

            return [
                'ok' => false,
                'retryable' => false,
                'status' => 'rejected',
                'remote_id' => null,
                'request' => ['url' => $url, 'action' => $action, 'payload' => $payload, 'http_status' => $resp->status()],
                'response_raw' => $data,
                'response_ui' => ['type' => 'error', 'message' => (string)$msg],
            ];
        }

        return [
            'ok' => true,
            'retryable' => false,
            'status' => 'inprogress',
            'remote_id' => (string)($this->extractIdFromSuccess($data) ?? ''),
            'request' => ['url' => $url, 'action' => $action, 'payload' => $payload, 'http_status' => $resp->status()],
            'response_raw' => $data,
            'response_ui' => ['type' => 'success', 'message' => 'OK'],
        ];
    }

    private function extractIdFromSuccess(array $data): ?string
    {
        // غالبًا: SUCCESS.0.ID أو SUCCESS.0.REFERENCEID أو ID
        $id = data_get($data, 'SUCCESS.0.ID')
            ?? data_get($data, 'SUCCESS.0.REFERENCEID')
            ?? data_get($data, 'ID');

        if ($id === null) return null;
        $s = trim((string)$id);
        return $s === '' ? null : $s;
    }

    private function buildRequestXml(array $params): string
    {
        $xml = new \SimpleXMLElement('<PARAMETERS/>');
        foreach ($params as $k => $v) {
            $xml->addChild((string)$k, htmlspecialchars((string)$v));
        }
        return $xml->asXML() ?: '';
    }

    private function normalizeFields($order): array
    {
        $fields = [];
        if (is_array($order->params ?? null)) {
            $fields = $order->params['fields'] ?? $order->params['required'] ?? [];
        }
        return is_array($fields) ? $fields : [];
    }

    private function mapImeiFields(array $fields): array
    {
        // imei.us docs expects: MODELID, PROVIDERID, MEP, PIN, KBH, PRD, TYPE, REFERENCE, LOCKS
        $map = [
            'modelid' => 'MODELID',
            'model_id' => 'MODELID',
            'providerid' => 'PROVIDERID',
            'provider_id' => 'PROVIDERID',
            'mep' => 'MEP',
            'pin' => 'PIN',
            'kbh' => 'KBH',
            'prd' => 'PRD',
            'type' => 'TYPE',
            'reference' => 'REFERENCE',
            'locks' => 'LOCKS',
        ];

        $out = [];

        foreach ($fields as $k => $v) {
            $key = strtolower(trim((string)$k));
            $val = is_scalar($v) ? trim((string)$v) : '';
            if ($val === '') continue;

            if (isset($map[$key])) {
                $out[$map[$key]] = $val;
            }
        }

        return $out;
    }

    /* =========================
     * PLACE
     * ========================= */

    public function placeImeiOrder(ApiProvider $p, ImeiOrder $order): array
    {
        $serviceId = (string)($order->service?->remote_id ?? '');
        $imei = (string)($order->device ?? '');

        $fields = $this->normalizeFields($order);
        $mapped = $this->mapImeiFields($fields);

        // docs uses ID as service id
        $params = array_merge([
            'ID' => $serviceId,
            'IMEI' => $imei,
        ], $mapped);

        $res = $this->call($p, 'placeimeiorder', $params);

        // enrich UI
        if (($res['ok'] ?? false) === true) {
            $rid = $this->extractIdFromSuccess($res['response_raw'] ?? []) ?? '';
            $res['remote_id'] = $rid ?: ($res['remote_id'] ?? null);
            $res['response_ui'] = [
                'type' => 'success',
                'message' => 'Order submitted',
                'reference_id' => $res['remote_id'],
            ];
        }

        return $res;
    }

    public function placeServerOrder(ApiProvider $p, ServerOrder $order): array
    {
        $serviceId = (string)($order->service?->remote_id ?? '');
        $qty = (int)($order->quantity ?? 1);
        if ($qty < 1) $qty = 1;

        $fields = $this->normalizeFields($order);

        // docs says REQUIRED is JSON string
        $params = [
            'ID' => $serviceId,
            'QUANTITY' => $qty,
        ];

        if (!empty($fields)) {
            $params['REQUIRED'] = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $res = $this->call($p, 'placeserverorder', $params);

        if (($res['ok'] ?? false) === true) {
            $rid = $this->extractIdFromSuccess($res['response_raw'] ?? []) ?? '';
            $res['remote_id'] = $rid ?: ($res['remote_id'] ?? null);
            $res['response_ui'] = [
                'type' => 'success',
                'message' => 'Order submitted',
                'reference_id' => $res['remote_id'],
            ];
        }

        return $res;
    }

    public function placeFileOrder(ApiProvider $p, FileOrder $order): array
    {
        $serviceId = (string)($order->service?->remote_id ?? '');
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

        if ($filename === '') $filename = basename($path);

        $content = Storage::get($path);

        $params = [
            'ID' => $serviceId,
            'FILENAME' => $filename,
            'FILEDATA' => base64_encode($content),
        ];

        $res = $this->call($p, 'placefileorder', $params);

        if (($res['ok'] ?? false) === true) {
            $rid = $this->extractIdFromSuccess($res['response_raw'] ?? []) ?? '';
            $res['remote_id'] = $rid ?: ($res['remote_id'] ?? null);
            $res['response_ui'] = [
                'type' => 'success',
                'message' => 'Order submitted',
                'reference_id' => $res['remote_id'],
            ];
        }

        return $res;
    }

    /* =========================
     * GET STATUS
     * ========================= */

    public function getImeiOrder(ApiProvider $p, string $id): array
    {
        $res = $this->call($p, 'getimeiorder', ['ID' => $id]);

        // map status (simple, tolerant)
        $mapped = $this->mapStatus($res['response_raw'] ?? []);
        $res['status'] = $mapped['status'];
        $res['response_ui'] = $mapped['ui'] + ['reference_id' => $id];

        return $res;
    }

    public function getServerOrder(ApiProvider $p, string $id): array
    {
        $res = $this->call($p, 'getserverorder', ['ID' => $id]);

        $mapped = $this->mapStatus($res['response_raw'] ?? []);
        $res['status'] = $mapped['status'];
        $res['response_ui'] = $mapped['ui'] + ['reference_id' => $id];

        return $res;
    }

    public function getFileOrder(ApiProvider $p, string $id): array
    {
        $res = $this->call($p, 'getfileorder', ['ID' => $id]);

        $mapped = $this->mapStatus($res['response_raw'] ?? []);
        $res['status'] = $mapped['status'];
        $res['response_ui'] = $mapped['ui'] + ['reference_id' => $id];

        return $res;
    }

    private function mapStatus(array $raw): array
    {
        // Try common fields:
        $statusVal =
            data_get($raw, 'SUCCESS.0.STATUS')
            ?? data_get($raw, 'SUCCESS.0.status')
            ?? data_get($raw, 'STATUS')
            ?? data_get($raw, 'status');

        $statusStr = strtolower(trim((string)$statusVal));

        // If provider uses numeric statuses, keep best-effort:
        // success: 4 / done / delivered
        // rejected: 3 / reject / error
        // else inprogress
        $status = 'inprogress';

        if ($statusStr === '4' || str_contains($statusStr, 'success') || str_contains($statusStr, 'delivered') || str_contains($statusStr, 'done')) {
            $status = 'success';
        } elseif ($statusStr === '3' || str_contains($statusStr, 'reject') || str_contains($statusStr, 'fail') || str_contains($statusStr, 'error')) {
            $status = 'rejected';
        } elseif ($statusStr === '0' || str_contains($statusStr, 'pending') || str_contains($statusStr, 'waiting')) {
            $status = 'inprogress'; // ✅ لا نرجعها waiting بعد remote_id
        }

        // Try code/result field
        $result =
            data_get($raw, 'SUCCESS.0.CODE')
            ?? data_get($raw, 'SUCCESS.0.RESPONSE')
            ?? data_get($raw, 'SUCCESS.0.MESSAGE')
            ?? data_get($raw, 'SUCCESS.0.DETAILS');

        $resultText = is_string($result) ? $result : (is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '');

        $uiType = $status === 'success' ? 'success' : ($status === 'rejected' ? 'error' : 'info');
        $uiMsg = $status === 'success' ? 'Result available' : ($status === 'rejected' ? 'Rejected' : 'In progress');

        return [
            'status' => $status,
            'ui' => [
                'type' => $uiType,
                'message' => $uiMsg,
                'result_text' => $resultText,
            ],
        ];
    }
}