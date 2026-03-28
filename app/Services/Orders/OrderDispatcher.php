<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use App\Models\SmmOrder;
use Illuminate\Support\Facades\Log;

class OrderDispatcher
{
    public function __construct(
        private OrderSender $sender,
        private OrderFinanceService $finance
    ) {}

    public function send(string $kind, int $orderId): void
    {
        $kind = strtolower(trim($kind));

        try {
            match ($kind) {
                'imei'   => $this->dispatchImei(ImeiOrder::findOrFail($orderId)),
                'server' => $this->dispatchServer(ServerOrder::findOrFail($orderId)),
                'file'   => $this->dispatchFile(FileOrder::findOrFail($orderId)),
                'smm'    => $this->dispatchSmm(SmmOrder::findOrFail($orderId)),
                default  => Log::warning('Unknown order kind', ['kind' => $kind, 'order_id' => $orderId]),
            };
        } catch (\Throwable $e) {
            Log::error('OrderDispatcher send failed', [
                'kind' => $kind,
                'order_id' => $orderId,
                'err' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    private function resolveProvider($order): ?ApiProvider
    {
        $order->load(['service', 'provider']);

        if (!$order->service || !$order->service->supplier_id || !$order->service->remote_id) {
            $order->status = 'rejected';
            $order->response = ['type' => 'error', 'message' => 'SERVICE NOT LINKED TO PROVIDER'];
            $order->replied_at = now();
            $order->processing = false;
            $order->save();

            $this->finance->refundOrderIfNeeded($order, 'dispatch_rejected_service_not_linked');
            return null;
        }

        $provider = ApiProvider::find((int)$order->service->supplier_id);
        if (!$provider) {
            $order->status = 'rejected';
            $order->response = ['type' => 'error', 'message' => 'PROVIDER MISSING'];
            $order->replied_at = now();
            $order->processing = false;
            $order->save();

            $this->finance->refundOrderIfNeeded($order, 'dispatch_rejected_provider_missing');
            return null;
        }

        if ((int)$provider->active !== 1) {
            $order->status = 'waiting';
            $order->processing = false;
            $order->response = ['type' => 'queued', 'message' => 'PROVIDER DISABLED'];
            $order->save();
            return null;
        }

        $order->supplier_id = $provider->id;
        $order->save();

        return $provider;
    }

    private function saveGatewayResult($order, array $result): void
    {
        $req = (array)($order->request ?? []);
        $req['request'] = $result['request'] ?? null;
        $req['response_raw'] = $result['response_raw'] ?? null;
        $req['last_gateway_result_at'] = now()->toDateTimeString();

        $order->request = $req;

        $ui = $result['response_ui'] ?? null;
        if (is_array($ui)) {
            $existing = $order->response;
            if (is_string($existing)) {
                $decoded = json_decode($existing, true);
                $existing = is_array($decoded) ? $decoded : ['raw' => $existing];
            } elseif (!is_array($existing)) {
                $existing = [];
            }

            $order->response = array_merge($existing, $ui);
        }
    }

    private function classifyFailure(string $message, int $httpStatus = 0, ?string $contentType = null): array
    {
        $m = strtolower(trim($message));
        $ct = strtolower((string)$contentType);

        if (
            str_contains($m, 'could not resolve') ||
            str_contains($m, 'name or service not known') ||
            str_contains($m, 'no such host') ||
            str_contains($m, 'invalid url') ||
            str_contains($m, 'malformed') ||
            str_contains($m, 'curl error 3') ||
            str_contains($m, 'curl error 6')
        ) {
            return ['rejected', 'INVALID URL - Check provider URL/api_path', true];
        }

        if (
            $httpStatus === 401 || $httpStatus === 403 ||
            str_contains($m, 'unauthorized') ||
            str_contains($m, 'forbidden') ||
            (str_contains($m, 'auth') && str_contains($m, 'fail')) ||
            str_contains($m, 'invalid key') ||
            str_contains($m, 'invalid api key') ||
            str_contains($m, 'api key invalid') ||
            str_contains($m, 'wrong api key') ||
            str_contains($m, 'access key invalid') ||
            str_contains($m, 'invalid username') ||
            str_contains($m, 'login failed') ||
            str_contains($m, 'authentication failed')
        ) {
            return ['rejected', 'AUTH FAILED - Check username/api_key/auth_mode', true];
        }

        if (
            str_contains($m, 'no enough balance') ||
            str_contains($m, 'not enough balance') ||
            str_contains($m, 'insufficient balance') ||
            str_contains($m, 'insufficient funds') ||
            str_contains($m, 'low balance') ||
            str_contains($m, 'provider balance') ||
            (str_contains($m, 'balance') && str_contains($m, 'not enough')) ||
            (str_contains($m, 'credit') && str_contains($m, 'not enough')) ||
            (str_contains($m, 'insufficient') && str_contains($m, 'credit')) ||
            str_contains($m, 'your balance is low') ||
            str_contains($m, 'not enough credit') ||
            str_contains($m, 'insufficient credits') ||
            str_contains($m, 'wallet balance low')
        ) {
            return ['waiting', 'NO ENOUGH BALANCE AT PROVIDER', false];
        }

        if (
            str_contains($m, 'maintenance') ||
            str_contains($m, 'under maintenance') ||
            str_contains($m, 'service unavailable') ||
            str_contains($m, 'temporarily unavailable') ||
            str_contains($m, 'provider down') ||
            str_contains($m, 'server busy') ||
            str_contains($m, 'try again later')
        ) {
            return ['waiting', 'PROVIDER MAINTENANCE / DOWN', false];
        }

        if (
            str_contains($m, 'ip blocked') ||
            str_contains($m, 'whitelist') ||
            str_contains($m, 'access denied') ||
            str_contains($m, 'cloudflare') ||
            str_contains($m, '<!doctype html') ||
            str_contains($m, '<html') ||
            ($httpStatus === 503 && (str_contains($ct, 'text/html') || str_contains($m, 'service unavailable')))
        ) {
            return ['rejected', 'IP BLOCKED - Reset Provider IP', true];
        }

        if (
            str_contains($m, 'service disabled') ||
            str_contains($m, 'service is disabled') ||
            str_contains($m, 'service not active') ||
            str_contains($m, 'disabled service') ||
            str_contains($m, 'invalid service') ||
            str_contains($m, 'invalid service id') ||
            str_contains($m, 'service id invalid') ||
            str_contains($m, 'service not found') ||
            str_contains($m, 'unknown service') ||
            str_contains($m, 'tool not found') ||
            str_contains($m, 'invalid tool')
        ) {
            return ['rejected', 'INVALID / DISABLED SERVICE', true];
        }

        if (
            str_contains($m, 'required field') ||
            str_contains($m, 'field is required') ||
            str_contains($m, 'is required') ||
            (str_contains($m, 'parameter') && str_contains($m, 'required')) ||
            (str_contains($m, 'missing') && str_contains($m, 'field'))
        ) {
            return ['rejected', 'REQUIRED FIELD MISSING', true];
        }

        if (
            str_contains($m, 'timed out') ||
            str_contains($m, 'timeout') ||
            str_contains($m, 'connection refused') ||
            str_contains($m, 'failed to connect') ||
            str_contains($m, 'curl error 7') ||
            str_contains($m, 'curl error 28')
        ) {
            return ['waiting', 'TIMEOUT - Provider not responding', false];
        }

        if ($httpStatus >= 500) {
            return ['waiting', 'PROVIDER DOWN - Try again later', false];
        }

        if ($httpStatus >= 400 && $httpStatus < 500) {
            return ['rejected', 'REQUEST REJECTED - Check request fields', true];
        }

        return ['waiting', 'PROVIDER ERROR', false];
    }

    private function isTerminalStatus(string $status): bool
    {
        return in_array($status, ['success', 'rejected', 'cancelled'], true);
    }

    private function normalizeStatus(?string $status): string
    {
        $status = strtolower(trim((string)$status));
        if ($status === 'canceled') {
            $status = 'cancelled';
        }

        if (!in_array($status, ['waiting', 'inprogress', 'success', 'rejected', 'cancelled'], true)) {
            return 'rejected';
        }

        return $status;
    }

    private function applyResult($order, array $result): void
    {
        $this->saveGatewayResult($order, $result);

        $httpStatus = (int) data_get($result, 'request.http_status', 0);
        $contentType = null;
        $raw = $result['response_raw'] ?? null;
        if (is_array($raw)) {
            $ct = data_get($raw, 'headers.content-type.0');
            if (is_string($ct)) {
                $contentType = $ct;
            }
        }

        $gatewayUiMessage = trim((string) data_get($result, 'response_ui.message', ''));
        $rawException = trim((string) data_get($result, 'response_raw.exception', ''));
        $fallbackMsg = trim((string)($result['error'] ?? $result['message'] ?? ''));

        $baseMsg = $gatewayUiMessage;

        if ($baseMsg === '' || in_array(strtolower($baseMsg), ['temporary provider error, queued.', 'provider error'], true)) {
            if ($rawException !== '') {
                $baseMsg = $rawException;
            } elseif ($fallbackMsg !== '') {
                $baseMsg = $fallbackMsg;
            }
        }

        if ($baseMsg === '') {
            $baseMsg = 'PROVIDER ERROR';
        }

        [$classStatus, $shortMsg, $strictReject] = ['waiting', 'PROVIDER ERROR', false];

        if (($result['ok'] ?? false) !== true) {
            [$classStatus, $shortMsg, $strictReject] = $this->classifyFailure($baseMsg, $httpStatus, $contentType);
        }

        $retryable = (bool)($result['retryable'] ?? false);

        if ($retryable && $strictReject) {
            $order->status = 'rejected';
            $order->processing = false;
            $order->replied_at = now();

            $resp = is_array($order->response) ? $order->response : [];
            $resp['type'] = 'error';
            $resp['message'] = $shortMsg;
            $order->response = $resp;

            $order->save();
            $this->finance->refundOrderIfNeeded($order, 'dispatch_rejected');
            return;
        }

        if ($retryable) {
            $order->status = 'waiting';
            $order->processing = false;

            $resp = is_array($order->response) ? $order->response : [];
            $resp['type'] = 'queued';
            $resp['message'] = $shortMsg;
            $order->response = $resp;

            $order->save();
            return;
        }

        if (($result['ok'] ?? false) === true) {
            $finalStatus = $this->normalizeStatus((string)($result['status'] ?? 'inprogress'));

            $order->remote_id = $result['remote_id'] ?? $order->remote_id;
            $order->status = $finalStatus;
            $order->processing = false;

            if ($this->isTerminalStatus($finalStatus)) {
                $order->replied_at = now();
            }

            $resp = is_array($order->response) ? $order->response : [];
            if (!isset($resp['message']) || trim((string)$resp['message']) === '') {
                $resp['message'] = $finalStatus === 'success' ? 'OK' : ucfirst($finalStatus);
            }
            $resp['type'] = $finalStatus === 'success'
                ? 'success'
                : ($finalStatus === 'inprogress' || $finalStatus === 'waiting' ? 'info' : 'error');

            $order->response = $resp;
            $order->save();

            if (in_array($finalStatus, ['rejected', 'cancelled'], true)) {
                $this->finance->refundOrderIfNeeded($order, 'dispatch_' . $finalStatus);
            }

            return;
        }

        $finalStatus = $this->normalizeStatus((string)($result['status'] ?? $classStatus ?? 'rejected'));

        $order->status = $finalStatus;
        $order->processing = false;

        if ($this->isTerminalStatus($finalStatus)) {
            $order->replied_at = now();
        }

        $resp = is_array($order->response) ? $order->response : [];
        $resp['type'] = $finalStatus === 'waiting'
            ? 'queued'
            : ($finalStatus === 'success' ? 'success' : 'error');

        if ($finalStatus === 'waiting') {
            $resp['message'] = $shortMsg;
        } elseif ($gatewayUiMessage !== '') {
            $resp['message'] = $gatewayUiMessage;
        } elseif ($finalStatus === 'success') {
            $resp['message'] = 'OK';
        } else {
            $resp['message'] = $shortMsg;
        }

        $order->response = $resp;
        $order->save();

        if (in_array($finalStatus, ['rejected', 'cancelled'], true)) {
            $this->finance->refundOrderIfNeeded($order, 'dispatch_' . $finalStatus);
        }
    }

    private function applyDispatchException($order, \Throwable $e): void
    {
        $msg = trim((string)$e->getMessage());
        $httpStatus = 0;
        if (preg_match('/\bhttp\s*([0-9]{3})\b/i', $msg, $mm)) $httpStatus = (int)$mm[1];
        if ($httpStatus === 0 && preg_match('/\bstatus\s*code\s*([0-9]{3})\b/i', $msg, $mm)) $httpStatus = (int)$mm[1];

        [$status, $shortMsg, $strictReject] = $this->classifyFailure($msg, $httpStatus, null);

        if ($strictReject) {
            $order->status = 'rejected';
            $order->processing = false;
            $order->replied_at = now();
            $order->response = ['type' => 'error', 'message' => $shortMsg];
            $order->save();
            $this->finance->refundOrderIfNeeded($order, 'dispatch_rejected');
            return;
        }

        $order->status = 'waiting';
        $order->processing = false;
        $order->response = ['type' => 'queued', 'message' => $shortMsg];
        $order->save();
    }

    public function dispatchImei(ImeiOrder $order): void
    {
        $provider = $this->resolveProvider($order);
        if (!$provider) return;

        $order->status = 'inprogress';
        $order->processing = true;
        $order->save();

        try {
            $result = $this->sender->sendImei($provider, $order);
            $this->applyResult($order, $result);
        } catch (\Throwable $e) {
            Log::error('dispatchImei failed', ['order_id' => $order->id, 'err' => $e->getMessage()]);
            $this->applyDispatchException($order, $e);
        }
    }

    public function dispatchServer(ServerOrder $order): void
    {
        $provider = $this->resolveProvider($order);
        if (!$provider) return;

        $order->status = 'inprogress';
        $order->processing = true;
        $order->save();

        try {
            $result = $this->sender->sendServer($provider, $order);
            $this->applyResult($order, $result);
        } catch (\Throwable $e) {
            Log::error('dispatchServer failed', ['order_id' => $order->id, 'err' => $e->getMessage()]);
            $this->applyDispatchException($order, $e);
        }
    }

    public function dispatchFile(FileOrder $order): void
    {
        $provider = $this->resolveProvider($order);
        if (!$provider) return;

        $order->status = 'inprogress';
        $order->processing = true;
        $order->save();

        try {
            $result = $this->sender->sendFile($provider, $order);
            $this->applyResult($order, $result);
        } catch (\Throwable $e) {
            Log::error('dispatchFile failed', ['order_id' => $order->id, 'err' => $e->getMessage()]);
            $this->applyDispatchException($order, $e);
        }
    }

    public function dispatchSmm(SmmOrder $order): void
    {
        $provider = $this->resolveProvider($order);
        if (!$provider) return;

        $order->status = 'inprogress';
        $order->processing = true;
        $order->save();

        try {
            $result = $this->sender->sendSmm($provider, $order);
            $this->applyResult($order, $result);
        } catch (\Throwable $e) {
            Log::error('dispatchSmm failed', ['order_id' => $order->id, 'err' => $e->getMessage()]);
            $this->applyDispatchException($order, $e);
        }
    }
}