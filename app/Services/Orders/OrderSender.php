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

    /**
     * Dispatch IMEI order based on provider type.
     * Current project supports Dhru-style gateways.
     */
    public function sendImei(ApiProvider $provider, ImeiOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'dhru')));

        // ✅ For now: all supported providers use Dhru gateway style
        // Later you can add:
        // if ($type === 'unlockbase') return $this->unlockbase->placeImeiOrder(...)

        return $this->dhru->placeImeiOrder($provider, $order);
    }

    /**
     * Dispatch Server order based on provider type.
     */
    public function sendServer(ApiProvider $provider, ServerOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'dhru')));
        return $this->dhru->placeServerOrder($provider, $order);
    }

    /**
     * Dispatch File order based on provider type.
     */
    public function sendFile(ApiProvider $provider, FileOrder $order): array
    {
        $type = strtolower(trim((string)($provider->type ?? 'dhru')));
        return $this->dhru->placeFileOrder($provider, $order);
    }
}
