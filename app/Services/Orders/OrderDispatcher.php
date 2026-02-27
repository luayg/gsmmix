<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OrderDispatcher
{
    public function __construct(
        private OrderSender $sender
    ) {}

    public function send(string $kind, int $orderId): void
    {
        $kind = strtolower(trim($kind));

        try {
            match ($kind) {
                'imei'   => $this->dispatchImei(ImeiOrder::findOrFail($orderId)),
                'server' => $this->dispatchServer(ServerOrder::findOrFail($orderId)),
                'file'   => $this->dispatchFile(FileOrder::findOrFail($orderId)),
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

    private function refundIfNeeded($order, string $reason): void
    {
        $req = (array)($order->request ?? []);
        if (!empty($req['refunded_at'])) return;

        $uid = (int)($order->user_id ?? 0);
        if ($uid <= 0) return;

        $amount = (float)($req['charged_amount'] ?? 0);
        if ($amount <= 0) return;

        DB::transaction(function () use ($order, $uid, $amount, $reason, $req) {
            $u = User::query()->lockForUpdate()->find($uid);
            if (!$u) return;

            $u->balance = (float)($u->balance ?? 0) + $amount;
            $u->save();

            $req['refunded_at'] = now()->toDateTimeString();
            $req['refunded_amount'] = $amount;
            $req['refunded_reason'] = $reason;

            $order->request = $req;
            $order->save();
        });
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

            $this->refundIfNeeded($order, 'dispatch_rejected_service_not_linked');
            return null;
        }

        $provider = ApiProvider::find((int)$order->service->supplier_id);
        if (!$provider) {
            $order->status = 'rejected';
            $order->response = ['type' => 'error', 'message' => 'PROVIDER MISSING'];
            $order->replied_at = now();
            $order->processing = false;
            $order->save();

            $this->refundIfNeeded($order, 'dispatch_rejected_provider_missing');
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
        $order->request = array_merge((array)($order->request ?? []), [
            'request'      => $result['request'] ?? null,
            'response_raw' => $result['response_raw'] ?? null,
        ]);

        // keep ui but we may override message later
        $order->response = $result['response_ui'] ?? $order->response;
    }

    /**
     * Strict short English classifier.
     * Returns: [finalStatus, uiMessage, strictReject]
     */
    private function classifyFailure(string $message, int $httpStatus = 0, ?string $contentType = null): array
    {
        $m = strtolower(trim($message));
        $ct = strtolower((string)$contentType);

        // detect invalid URL / DNS
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

        // auth
        if (
            $httpStatus === 401 || $httpStatus === 403 ||
            str_contains($m, 'unauthorized') ||
            str_contains($m, 'forbidden') ||
            (str_contains($m, 'auth') && str_contains($m, 'fail')) ||
            str_contains($m, 'invalid key') ||
            (str_contains($m, 'api key') && str_contains($m, 'invalid'))
        ) {
            return ['rejected', 'AUTH FAILED - Check username/api_key/auth_mode', true];
        }

        // IP blocked / WAF / HTML 503
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

        // timeout / connect => waiting
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

        // provider down => waiting
        if ($httpStatus >= 500) {
            return ['waiting', 'PROVIDER DOWN - Try again later', false];
        }

        // other 4xx => reject
        if ($httpStatus >= 400 && $httpStatus < 500) {
            return ['rejected', 'REQUEST REJECTED - Check request fields', true];
        }

        return ['waiting', 'PROVIDER ERROR', false];
    }

    private function applyResult($order, array $result): void
    {
        $this->saveGatewayResult($order, $result);

        // derive http status/content-type if present
        $httpStatus = (int) data_get($result, 'request.http_status', 0);
        $contentType = null;
        $raw = $result['response_raw'] ?? null;
        if (is_array($raw)) {
            // sometimes gateways store headers
            $ct = data_get($raw, 'headers.content-type.0');
            if (is_string($ct)) $contentType = $ct;
        }

        // If gateway already provided a short ui message, keep it; otherwise classify
        $uiMsg = (string) data_get($result, 'response_ui.message', '');
        $fallbackMsg = (string)($result['error'] ?? $result['message'] ?? '');
        $baseMsg = trim($uiMsg !== '' ? $uiMsg : ($fallbackMsg !== '' ? $fallbackMsg : 'PROVIDER ERROR'));

        [$classStatus, $shortMsg] = ['waiting', 'PROVIDER ERROR'];
        $strictReject = false;

        // Only classify when not ok OR retryable
        if (($result['ok'] ?? false) !== true) {
            [$classStatus, $shortMsg, $strictReject] = $this->classifyFailure($baseMsg, $httpStatus, $contentType);
        }

        $retryable = (bool)($result['retryable'] ?? false);

        // ✅ Retryable but strictReject => reject (IP/URL/AUTH)
        if ($retryable && $strictReject) {
            $order->status = 'rejected';
            $order->processing = false;
            $order->replied_at = now();

            $resp = is_array($order->response) ? $order->response : [];
            $resp['type'] = 'error';
            $resp['message'] = $shortMsg;
            $order->response = $resp;

            $order->save();
            $this->refundIfNeeded($order, 'dispatch_rejected');
            return;
        }

        // ✅ Retryable => waiting (short msg)
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

        // ✅ Success
        if (($result['ok'] ?? false) === true) {
            $order->remote_id = $result['remote_id'] ?? $order->remote_id;
            $order->status = $result['status'] ?? 'inprogress';
            $order->processing = false;

            // Keep success message short if exists
            $resp = is_array($order->response) ? $order->response : [];
            if (!empty($resp)) {
                if (!isset($resp['message']) || trim((string)$resp['message']) === '') {
                    $resp['message'] = 'OK';
                }
                $order->response = $resp;
            }

            $order->save();
            return;
        }

        // ❌ Not ok and not retryable => rejected/cancelled
        $finalStatus = $result['status'] ?? $classStatus ?? 'rejected';
        $finalStatus = strtolower(trim((string)$finalStatus));
        if ($finalStatus === 'canceled') $finalStatus = 'cancelled';

        if (!in_array($finalStatus, ['rejected','cancelled','waiting','inprogress','success'], true)) {
            $finalStatus = 'rejected';
        }

        $order->status = $finalStatus;
        $order->processing = false;
        $order->replied_at = now();

        $resp = is_array($order->response) ? $order->response : [];
        $resp['type'] = ($finalStatus === 'waiting') ? 'queued' : (($finalStatus === 'success') ? 'success' : 'error');
        $resp['message'] = ($finalStatus === 'waiting') ? $shortMsg : ($finalStatus === 'success' ? 'OK' : $shortMsg);
        $order->response = $resp;

        $order->save();

        if (in_array($finalStatus, ['rejected', 'cancelled'], true)) {
            $this->refundIfNeeded($order, 'dispatch_' . $finalStatus);
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
            $this->refundIfNeeded($order, 'dispatch_rejected');
            return;
        }

        // waiting
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
}