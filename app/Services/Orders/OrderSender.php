<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;

class OrderSender
{
    public function __construct(
        private DhruOrderGateway $dhru,
        private WebxOrderGateway $webx,
        private UnlockbaseOrderGateway $unlockbase,
        private GsmhubOrderGateway $gsmhub
    ) {}

    public function sendImei(ApiProvider $provider, ImeiOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'dhru')));

        return match ($type) {
            'webx'       => $this->webx->placeImeiOrder($provider, $order),
            'unlockbase' => $this->unlockbase->placeImeiOrder($provider, $order),
            'gsmhub'     => $this->gsmhub->placeImeiOrder($provider, $order),
            default      => $this->dhru->placeImeiOrder($provider, $order),
        };
    }

    public function sendServer(ApiProvider $provider, ServerOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'dhru')));

        return match ($type) {
            'webx'   => $this->webx->placeServerOrder($provider, $order),
            'gsmhub' => $this->gsmhub->placeServerOrder($provider, $order),
            default  => $this->dhru->placeServerOrder($provider, $order),
        };
    }

    public function sendFile(ApiProvider $provider, FileOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'dhru')));

        return match ($type) {
            'webx'   => $this->webx->placeFileOrder($provider, $order),
            'gsmhub' => $this->gsmhub->placeFileOrder($provider, $order),
            default  => $this->dhru->placeFileOrder($provider, $order),
        };
    }
}