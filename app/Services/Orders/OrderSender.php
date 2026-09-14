<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use App\Models\SmmOrder;

class OrderSender
{
    public function __construct(
        private DhruOrderGateway $dhru,
        private WebxOrderGateway $webx,
        private UnlockbaseOrderGateway $unlockbase,
        private GsmhubOrderGateway $gsmhub,
        private SimpleLinkOrderGateway $simpleLink,
        private SmmOrderGateway $smm
    ) {}

    public function sendImei(ApiProvider $provider, ImeiOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'dhru')));

        $result = match ($type) {
            'dhru'        => $this->dhru->placeImeiOrder($provider, $order),
            'webx'        => $this->webx->placeImeiOrder($provider, $order),
            'unlockbase'  => $this->unlockbase->placeImeiOrder($provider, $order),
            'gsmhub'      => $this->gsmhub->placeImeiOrder($provider, $order),
            'simple_link' => $this->simpleLink->placeImeiOrder($provider, $order),
            default       => $this->unsupported($type, 'imei'),
        };

        return $this->guardSubmissionResult($result, 'imei', $type);
    }

    public function sendServer(ApiProvider $provider, ServerOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'dhru')));

        $result = match ($type) {
            'dhru'   => $this->dhru->placeServerOrder($provider, $order),
            'webx'   => $this->webx->placeServerOrder($provider, $order),
            'gsmhub' => $this->gsmhub->placeServerOrder($provider, $order),
            default  => $this->unsupported($type, 'server'),
        };

        return $this->guardSubmissionResult($result, 'server', $type);
    }

    public function sendFile(ApiProvider $provider, FileOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'dhru')));

        $result = match ($type) {
            'dhru'   => $this->dhru->placeFileOrder($provider, $order),
            'webx'   => $this->webx->placeFileOrder($provider, $order),
            'gsmhub' => $this->gsmhub->placeFileOrder($provider, $order),
            default  => $this->unsupported($type, 'file'),
        };

        return $this->guardSubmissionResult($result, 'file', $type);
    }

    public function sendSmm(ApiProvider $provider, SmmOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'smm')));

        $result = match ($type) {
            'smm' => $this->smm->placeSmmOrder($provider, $order),
            default => $this->unsupported($type, 'smm'),
        };

        return $this->guardSubmissionResult($result, 'smm', $type);
    }

    /**
     * A provider may accept a submission but return malformed/ambiguous data.
     * Never report an asynchronous order as safely in-progress unless we received
     * a remote id. Blindly retrying that response can create a duplicate paid order,
     * so the result is held for manual review instead of being auto-dispatched again.
     * Synchronous Simple Link IMEI success is intentionally allowed without remote_id.
     */
    private function guardSubmissionResult(array $result, string $kind, string $providerType): array
    {
        $ok = ($result['ok'] ?? false) === true;
        $status = strtolower(trim((string)($result['status'] ?? '')));
        $remoteId = trim((string)($result['remote_id'] ?? ''));

        if (!$ok || $status !== 'inprogress' || $remoteId !== '') {
            return $result;
        }

        $request = $result['request'] ?? [];
        if (!is_array($request)) {
            $request = ['raw' => $request];
        }
        $request['dispatch_hold'] = true;
        $request['provider_type'] = $providerType !== '' ? $providerType : 'unknown';
        $request['order_kind'] = $kind;
        $request['contract_error'] = 'provider_ack_without_remote_id';

        $result['ok'] = false;
        $result['retryable'] = true;
        $result['status'] = 'waiting';
        $result['remote_id'] = null;
        $result['request'] = $request;
        $result['response_ui'] = [
            'type' => 'queued',
            'message' => 'Provider acknowledged the order without an order ID. Automatic retry is on hold to prevent a duplicate submission.',
        ];

        return $result;
    }

    private function unsupported(string $providerType, string $kind): array
    {
        $providerType = $providerType !== '' ? $providerType : 'unknown';
        $message = sprintf('UNSUPPORTED PROVIDER TYPE "%s" FOR %s ORDER', $providerType, strtoupper($kind));

        return [
            'ok' => false,
            'retryable' => false,
            'status' => 'rejected',
            'remote_id' => null,
            'request' => [
                'provider_type' => $providerType,
                'order_kind' => $kind,
                'http_status' => 0,
            ],
            'response_raw' => [
                'error' => 'unsupported_provider_type',
                'provider_type' => $providerType,
                'order_kind' => $kind,
            ],
            'response_ui' => [
                'type' => 'error',
                'message' => $message,
            ],
        ];
    }
}
