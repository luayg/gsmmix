<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use App\Services\Api\GsmhubClient;
use Illuminate\Support\Facades\Storage;

class GsmhubOrderGateway
{
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
        // imei.us expects: MODELID, PROVIDERID, MEP, PIN, KBH, PRD, TYPE, REFERENCE, LOCKS
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
            $val = trim((string)$v);
            if ($val === '') continue;
            if (isset($map[$key])) $out[$map[$key]] = $val;
        }
        return $out;
    }

    public function placeImeiOrder(ApiProvider $p, ImeiOrder $order): array
    {
        $client = GsmhubClient::fromProvider($p);

        $serviceId = (string)($order->service?->remote_id ?? '');
        $imei = (string)($order->device ?? '');

        $fields = $this->normalizeFields($order);

        $params = array_merge([
            'ID' => $serviceId,
            'IMEI' => $imei,
        ], $this->mapImeiFields($fields));

        $raw = $client->placeImeiOrder($params);
        $rid = (string)(data_get($raw, 'SUCCESS.0.ID') ?? data_get($raw, 'SUCCESS.0.REFERENCEID') ?? data_get($raw, 'ID') ?? '');

        return [
            'ok' => true,
            'retryable' => false,
            'status' => 'inprogress',
            'remote_id' => $rid,
            'request' => ['endpoint' => $client->endpoint(), 'action' => 'placeimeiorder', 'params' => $params],
            'response_raw' => $raw,
            'response_ui' => ['type' => 'success', 'message' => 'Order submitted', 'reference_id' => $rid],
        ];
    }

    public function getImeiOrder(ApiProvider $p, string $id): array
    {
        $client = GsmhubClient::fromProvider($p);
        $raw = $client->getImeiOrder($id);
        return $this->normalizeStatusResult($client->endpoint(), 'getimeiorder', ['ID' => $id], $raw, $id);
    }

    public function placeServerOrder(ApiProvider $p, ServerOrder $order): array
    {
        $client = GsmhubClient::fromProvider($p);

        $serviceId = (string)($order->service?->remote_id ?? '');
        $qty = (int)($order->quantity ?? 1);
        if ($qty < 1) $qty = 1;

        $fields = $this->normalizeFields($order);

        $params = [
            'ID' => $serviceId,
            'QUANTITY' => $qty,
        ];

        if (!empty($fields)) {
            // doc: REQUIRED is json string
            $params['REQUIRED'] = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $raw = $client->placeServerOrder($params);
        $rid = (string)(data_get($raw, 'SUCCESS.0.ID') ?? data_get($raw, 'SUCCESS.0.REFERENCEID') ?? data_get($raw, 'ID') ?? '');

        return [
            'ok' => true,
            'retryable' => false,
            'status' => 'inprogress',
            'remote_id' => $rid,
            'request' => ['endpoint' => $client->endpoint(), 'action' => 'placeserverorder', 'params' => $params],
            'response_raw' => $raw,
            'response_ui' => ['type' => 'success', 'message' => 'Order submitted', 'reference_id' => $rid],
        ];
    }

    public function getServerOrder(ApiProvider $p, string $id): array
    {
        $client = GsmhubClient::fromProvider($p);
        $raw = $client->getServerOrder($id);
        return $this->normalizeStatusResult($client->endpoint(), 'getserverorder', ['ID' => $id], $raw, $id);
    }

    public function placeFileOrder(ApiProvider $p, FileOrder $order): array
    {
        $client = GsmhubClient::fromProvider($p);

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

        $raw = $client->placeFileOrder($params);
        $rid = (string)(data_get($raw, 'SUCCESS.0.ID') ?? data_get($raw, 'SUCCESS.0.REFERENCEID') ?? data_get($raw, 'ID') ?? '');

        return [
            'ok' => true,
            'retryable' => false,
            'status' => 'inprogress',
            'remote_id' => $rid,
            'request' => ['endpoint' => $client->endpoint(), 'action' => 'placefileorder', 'params' => $params],
            'response_raw' => $raw,
            'response_ui' => ['type' => 'success', 'message' => 'Order submitted', 'reference_id' => $rid],
        ];
    }

    public function getFileOrder(ApiProvider $p, string $id): array
    {
        $client = GsmhubClient::fromProvider($p);
        $raw = $client->getFileOrder($id);
        return $this->normalizeStatusResult($client->endpoint(), 'getfileorder', ['ID' => $id], $raw, $id);
    }

    private function normalizeStatusResult(string $endpoint, string $action, array $params, array $raw, string $refId): array
    {
        $statusVal =
            data_get($raw, 'SUCCESS.0.STATUS')
            ?? data_get($raw, 'STATUS')
            ?? data_get($raw, 'SUCCESS.0.status')
            ?? data_get($raw, 'status');

        $s = strtolower(trim((string)$statusVal));
        $status = 'inprogress';

        if ($s === '4' || str_contains($s, 'success') || str_contains($s, 'delivered') || str_contains($s, 'done')) {
            $status = 'success';
        } elseif ($s === '3' || str_contains($s, 'reject') || str_contains($s, 'fail') || str_contains($s, 'error')) {
            $status = 'rejected';
        } else {
            $status = 'inprogress'; // لا نرجع waiting بعد remote_id
        }

        $result =
            data_get($raw, 'SUCCESS.0.CODE')
            ?? data_get($raw, 'SUCCESS.0.RESPONSE')
            ?? data_get($raw, 'SUCCESS.0.MESSAGE')
            ?? data_get($raw, 'SUCCESS.0.DETAILS');

        $resultText = is_string($result)
            ? $result
            : (is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '');

        return [
            'ok' => true,
            'retryable' => false,
            'status' => $status,
            'remote_id' => $refId,
            'request' => ['endpoint' => $endpoint, 'action' => $action, 'params' => $params],
            'response_raw' => $raw,
            'response_ui' => [
                'type' => $status === 'success' ? 'success' : ($status === 'rejected' ? 'error' : 'info'),
                'message' => $status === 'success' ? 'Result available' : ($status === 'rejected' ? 'Rejected' : 'In progress'),
                'reference_id' => $refId,
                'result_text' => $resultText,
            ],
        ];
    }
}