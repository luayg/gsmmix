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
            'webx'        => $this->webx->placeImeiOrder($provider, $order),
            'unlockbase'  => $this->unlockbase->placeImeiOrder($provider, $order),
            'gsmhub'      => $this->gsmhub->placeImeiOrder($provider, $order),
            'simple_link' => $this->simpleLink->placeImeiOrder($provider, $order),
            default       => $this->dhru->placeImeiOrder($provider, $order),
        };
    }

    public function sendServer(ApiProvider $provider, ServerOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'dhru')));

        return match ($type) {
            'webx'        => $this->webx->placeServerOrder($provider, $order),
            'gsmhub'      => $this->gsmhub->placeServerOrder($provider, $order),
            'simple_link' => $this->simpleLink->placeServerOrder($provider, $order),
            default       => $this->dhru->placeServerOrder($provider, $order),
        };
    }

    public function sendFile(ApiProvider $provider, FileOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'dhru')));

        return match ($type) {
            'webx'        => $this->webx->placeFileOrder($provider, $order),
            'gsmhub'      => $this->gsmhub->placeFileOrder($provider, $order),
            'simple_link' => $this->simpleLink->placeFileOrder($provider, $order),
            default       => $this->dhru->placeFileOrder($provider, $order),
        };
    }

    public function sendSmm(ApiProvider $provider, SmmOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'smm')));

        return match ($type) {
            'smm' => $this->smm->placeSmmOrder($provider, $order),
            default => [
                'ok' => false,
                'retryable' => false,
                'status' => 'rejected',
                'remote_id' => null,
                'request' => [
                    'url' => (string)($provider->url ?? ''),
                    'method' => 'POST',
                    'params' => [],
                    'http_status' => 0,
                ],
                'response_raw' => [
                    'raw' => 'UNSUPPORTED SMM PROVIDER TYPE',
                    'http_status' => 0,
                ],
                'response_ui' => [
                    'type' => 'error',
                    'message' => 'UNSUPPORTED SMM PROVIDER TYPE',
                ],
            ],
        };
    }
}