<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;

class OrderSender
{
    public function __construct(
        private DhruOrderGateway $dhru
    ) {}

    public function sendImei(ApiProvider $provider, ImeiOrder $order): array
    {
        return $this->dhru->placeImeiOrder($provider, $order);
    }

    public function sendServer(ApiProvider $provider, ServerOrder $order): array
    {
        return $this->dhru->placeServerOrder($provider, $order);
    }

    public function sendFile(ApiProvider $provider, FileOrder $order): array
    {
        return $this->dhru->placeFileOrder($provider, $order);
    }
}
