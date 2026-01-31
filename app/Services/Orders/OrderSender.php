<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\ImeiOrder;

class OrderSender
{
    public function __construct(
        private DhruOrderGateway $dhru
    ) {}

    public function sendImei(ApiProvider $provider, ImeiOrder $order): array
    {
        // حاليا Dhru فقط — لو عندك Providers أخرى لاحقاً نعمل switch على $provider->type
        return $this->dhru->placeImeiOrder($provider, $order);
    }
}
