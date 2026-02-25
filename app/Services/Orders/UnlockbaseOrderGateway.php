<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\ImeiOrder;
use Illuminate\Support\Facades\Http;

class UnlockbaseOrderGateway
{
    private function endpoint(ApiProvider $p): string
    {
        $ep = $p->params['endpoint'] ?? null;
        if (is_string($ep) && trim($ep) !== '') return trim($ep);

        // ✅ UnlockBase API v3.2 default endpoint
        return 'https://www.unlockbase.com/xml/api/v3';
    }

    private function resolveApiKey(ApiProvider $p): string
    {
        $key = trim((string)($p->api_key ?? ''));

        // fallback if key was mistakenly pasted into username
        if ($key === '') {
            $maybe = trim((string)($p->username ?? ''));
            $key = $maybe;
        }

        // remove surrounding parentheses if present
        $key = preg_replace('/^\((.+)\)$/', '$1', $key) ?? $key;
        return trim($key);
    }

    private function call(ApiProvider $p, string $action, array $params = []): array
    {
        $payload = array_merge([
            'Key'    => $this->resolveApiKey($p),
            'Action' => $action,
        ], $params);

        $resp = Http::asForm()
            ->timeout(60)
            ->retry(2, 500)
            ->post($this->endpoint($p), $payload);

        $xmlStr = (string)$resp->body();
        $xml = @simplexml_load_string($xmlStr, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            return [
                'ok' => false,
                'retryable' => true,
                'status' => 'waiting',
                'remote_id' => null,
                'request' => ['action' => $action, 'payload' => $payload, 'http_status' => $resp->status()],
                'response_raw' => ['raw' => $xmlStr],
                'response_ui' => ['type' => 'queued', 'message' => 'Invalid XML from UnlockBase, queued.'],
            ];
        }

        $arr = json_decode(json_encode($xml), true);
        if (!is_array($arr)) $arr = [];

        if (!empty($arr['Error'])) {
            $msg = trim((string)$arr['Error']);
            return [
                'ok' => false,
                'retryable' => false,
                'status' => 'rejected',
                'remote_id' => null,
                'request' => ['action' => $action, 'payload' => $payload, 'http_status' => $resp->status()],
                'response_raw' => $arr,
                'response_ui' => ['type' => 'error', 'message' => $msg !== '' ? $msg : 'UnlockBase error'],
            ];
        }

        return [
            'ok' => true,
            'retryable' => false,
            'status' => 'inprogress',
            'remote_id' => (string)($arr['ID'] ?? ''),
            'request' => ['action' => $action, 'payload' => $payload, 'http_status' => $resp->status()],
            'response_raw' => $arr,
            'response_ui' => [
                'type' => 'success',
                'message' => (string)($arr['Success'] ?? 'OK'),
                'reference_id' => (string)($arr['ID'] ?? ''),
            ],
        ];
    }

    public function placeImeiOrder(ApiProvider $p, ImeiOrder $order): array
    {
        $toolId = (string)($order->service?->remote_id ?? '');
        $imei   = (string)($order->device ?? '');

        $fields = is_array($order->params) ? ($order->params['fields'] ?? []) : [];
        if (!is_array($fields)) $fields = [];

        // map service_fields_# using service additional_fields if present
        $fields = $this->expandServiceFields($fields, $order);

        // normalize Email
        $fields = $this->ensureEmailAliases($fields);

        $payload = array_merge([
            'Tool'     => $toolId,
            'IMEI'     => $imei,
            'Comments' => (string)($order->comments ?? ''),
        ], $this->mapFieldsToUnlockbase($fields));

        return $this->call($p, 'PlaceOrder', $payload);
    }

    public function getImeiOrder(ApiProvider $p, string $remoteId): array
    {
        $res = $this->call($p, 'GetOrders', ['ID' => $remoteId]);

        if (($res['ok'] ?? false) !== true) return $res;

        $raw = $res['response_raw'] ?? [];
        $orders = $raw['Order'] ?? null;

        // normalize single order object
        if (is_array($orders) && array_key_exists('ID', $orders)) {
            $orders = [$orders];
        }

        if (!is_array($orders) || empty($orders)) {
            $res['retryable'] = true;
            $res['status'] = 'waiting';
            $res['response_ui'] = ['type' => 'queued', 'message' => 'Order not found yet, queued.', 'reference_id' => $remoteId];
            return $res;
        }

        $o = $orders[0];

        $status = strtolower(trim((string)($o['Status'] ?? 'waiting'))); // Waiting|Delivered|Canceled
        $available = strtolower(trim((string)($o['Available'] ?? 'false'))) === 'true';
        $codes = (string)($o['Codes'] ?? '');

        $mapped = match ($status) {
            'delivered' => $available ? 'success' : 'rejected',
            'canceled', 'cancelled' => 'cancelled',
            default => 'inprogress',
        };

        return array_merge($res, [
            'status' => $mapped,
            'remote_id' => (string)($o['ID'] ?? $remoteId),
            'response_ui' => [
                'type' => $mapped === 'success' ? 'success' : ($mapped === 'rejected' ? 'error' : 'info'),
                'message' => $mapped === 'success'
                    ? 'Result available'
                    : ($mapped === 'rejected' ? 'Delivered but unavailable' : 'In progress'),
                'reference_id' => (string)($o['ID'] ?? $remoteId),
                'unlockbase_status' => (string)($o['Status'] ?? ''),
                'result_text' => $codes,
            ],
        ]);
    }

    /* ============================
     * Helpers
     * ============================ */

    private function expandServiceFields(array $fields, ImeiOrder $order): array
    {
        $service = $order->service;
        if (!$service) return $fields;

        $defs = $service->additional_fields ?? null;
        if (is_string($defs)) {
            $decoded = json_decode($defs, true);
            $defs = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($defs) || empty($defs)) return $fields;

        foreach ($fields as $k => $v) {
            if (!preg_match('/^service_fields_(\d+)$/', (string)$k, $m)) continue;

            $idx = (int)$m[1] - 1;
            if ($idx < 0 || !isset($defs[$idx]) || !is_array($defs[$idx])) continue;

            $name = $defs[$idx]['name'] ?? $defs[$idx]['label'] ?? null;
            $name = is_string($name) ? strtolower(trim($name)) : null;
            if (!$name) continue;

            if (!array_key_exists($name, $fields)) {
                $fields[$name] = $v;
            }
        }

        return $fields;
    }

    private function ensureEmailAliases(array $fields): array
    {
        $val = $fields['email'] ?? $fields['Email'] ?? $fields['EMAIL'] ?? null;

        if (is_string($val) && trim($val) !== '') {
            $val = trim($val);
            $fields['email'] = $val;
            $fields['Email'] = $val;
            $fields['EMAIL'] = $val;
            return $fields;
        }

        foreach ($fields as $k => $v) {
            $s = trim((string)$v);
            if ($s !== '' && filter_var($s, FILTER_VALIDATE_EMAIL)) {
                $fields['email'] = $s;
                $fields['Email'] = $s;
                $fields['EMAIL'] = $s;
                break;
            }
        }

        return $fields;
    }

    private function mapFieldsToUnlockbase(array $fields): array
    {
        // v3.2 doc parameter names
        $map = [
            'email'    => 'Email',
            'comments' => 'Comments',
            'mobile'   => 'Mobile',
            'network'  => 'Network',
            'provider' => 'Provider',
            'pin'      => 'PIN',
            'kbh'      => 'KBH',
            'mep'      => 'MEP',
            'prd'      => 'PRD',
            'type'     => 'Type',
            'locks'    => 'Locks',
            'sms'      => 'SMS',
            'sn'       => 'SN',
            'secro'    => 'SecRO',
        ];

        $out = [];

        foreach ($fields as $k => $v) {
            $key = strtolower(trim((string)$k));

            // normalize common variants
            if ($key === 'provider-id' || $key === 'provider_id') $key = 'provider';
            if ($key === 'sec_ro') $key = 'secro';

            $val = is_scalar($v)
                ? (string)$v
                : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (trim($val) === '') continue;
            if (!isset($map[$key])) continue;

            $out[$map[$key]] = $val;
        }

        return $out;
    }
}