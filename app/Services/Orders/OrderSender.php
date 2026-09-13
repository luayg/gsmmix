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

        return match ($type) {
            'dhru'        => $this->dhru->placeImeiOrder($provider, $order),
            'webx'        => $this->webx->placeImeiOrder($provider, $order),
            'unlockbase'  => $this->unlockbase->placeImeiOrder($provider, $order),
            'gsmhub'      => $this->gsmhub->placeImeiOrder($provider, $order),
            'simple_link' => $this->simpleLink->placeImeiOrder($provider, $order),
            default       => $this->unsupported($type, 'imei'),
        };
    }

    public function sendServer(ApiProvider $provider, ServerOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'dhru')));

        return match ($type) {
            'dhru'   => $this->dhru->placeServerOrder($provider, $order),
            'webx'   => $this->webx->placeServerOrder($provider, $order),
            'gsmhub' => $this->gsmhub->placeServerOrder($provider, $order),
            default  => $this->unsupported($type, 'server'),
        };
    }

    public function sendFile(ApiProvider $provider, FileOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'dhru')));

        return match ($type) {
            'dhru'   => $this->dhru->placeFileOrder($provider, $order),
            'webx'   => $this->webx->placeFileOrder($provider, $order),
            'gsmhub' => $this->gsmhub->placeFileOrder($provider, $order),
            default  => $this->unsupported($type, 'file'),
        };
    }

    public function sendSmm(ApiProvider $provider, SmmOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'smm')));

        return match ($type) {
            'smm' => $this->smm->placeSmmOrder($provider, $order),
            default => $this->unsupported($type, 'smm'),
        };
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
