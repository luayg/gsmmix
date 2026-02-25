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

        // default: v3 as per your library snippet (can set to v2 if needed)
        return 'https://www.unlockbase.com/xml/api/v3';
    }

    private function call(ApiProvider $p, string $action, array $params = []): array
    {
        $payload = array_merge([
            'Key'    => (string)$p->api_key,
            'Action' => $action,
        ], $params);

        $resp = Http::asForm()->timeout(60)->retry(2, 500)->post($this->endpoint($p), $payload);

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
            'response_ui' => ['type' => 'success', 'message' => (string)($arr['Success'] ?? 'OK'), 'reference_id' => (string)($arr['ID'] ?? '')],
        ];
    }

    public function placeImeiOrder(ApiProvider $p, ImeiOrder $order): array
    {
        $toolId = (string)($order->service?->remote_id ?? '');
        $imei   = (string)($order->device ?? '');

        $fields = is_array($order->params) ? ($order->params['fields'] ?? []) : [];
        if (!is_array($fields)) $fields = [];

        // Map our stored fields -> UnlockBase params (Tool, IMEI, optional fields)
        $payload = array_merge([
            'Tool' => $toolId,
            'IMEI' => $imei,
            'Comments' => (string)($order->comments ?? ''),
        ], $this->mapFieldsToUnlockbase($fields));

        return $this->call($p, 'PlaceOrder', $payload);
    }

    public function getImeiOrder(ApiProvider $p, string $remoteId): array
    {
        // GetOrders supports filtering by ID
        $res = $this->call($p, 'GetOrders', ['ID' => $remoteId]);

        // If call failed, return as-is
        if (($res['ok'] ?? false) !== true) return $res;

        $raw = $res['response_raw'] ?? [];
        $orders = $raw['Order'] ?? null;

        // normalize single order
        if (is_array($orders) && array_key_exists('ID', $orders)) {
            $orders = [$orders];
        }
        if (!is_array($orders) || empty($orders)) {
            // treat as retryable (sometimes API returns empty if not indexed yet)
            $res['retryable'] = true;
            $res['status'] = 'waiting';
            $res['response_ui'] = ['type' => 'queued', 'message' => 'Order not found yet, queued.', 'reference_id' => $remoteId];
            return $res;
        }

        $o = $orders[0];
        $status = strtolower(trim((string)($o['Status'] ?? 'waiting')));
        $available = strtolower(trim((string)($o['Available'] ?? 'false'))) === 'true';
        $codes = (string)($o['Codes'] ?? '');

        $mapped = match ($status) {
            'delivered' => $available ? 'success' : 'rejected',
            'canceled', 'cancelled' => 'cancelled',
            default => 'inprogress', // waiting
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

    private function mapFieldsToUnlockbase(array $fields): array
    {
        // Accept common keys you already store in params.fields
        // If your UI uses different names, we can extend this map.
        $map = [
            'email' => 'Email',
            'comments' => 'Comments',
            'mobile' => 'Mobile',
            'network' => 'Network',
            'provider' => 'Provider',
            'pin' => 'PIN',
            'kbh' => 'KBH',
            'mep' => 'MEP',
            'prd' => 'PRD',
            'type' => 'Type',
            'locks' => 'Locks',
            'sms' => 'SMS',
        ];

        $out = [];
        foreach ($fields as $k => $v) {
            $key = strtolower(trim((string)$k));
            $val = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

            if ($val === '' || !isset($map[$key])) continue;
            $out[$map[$key]] = $val;
        }
        return $out;
    }
}