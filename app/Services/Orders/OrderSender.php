<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;

class OrderSender
{
    public function __construct(private DhruOrderGateway $dhru)
    {}

    public function send(string $kind, $order): array
    {
        $service = $order->service;
        if (!$service) {
            return [
                'status' => 'rejected',
                'response_raw' => 'Missing service',
            ];
        }

        $providerId = (int)($service->supplier_id ?? 0);
        $remoteServiceId = $service->remote_id ?? null;

        if ($providerId <= 0 || empty($remoteServiceId)) {
            return [
                'status' => 'rejected',
                'response_raw' => 'Service is not linked to API (supplier_id/remote_id missing).',
            ];
        }

        $provider = ApiProvider::query()->find($providerId);
        if (!$provider || (int)$provider->active !== 1) {
            return [
                'status' => 'rejected',
                'response_raw' => 'Provider inactive/missing',
            ];
        }

        // حالياً: نفترض DHRU فقط (انت شغال عليه الآن)
        // لاحقاً نضيف بوابات أخرى حسب type
        return $this->dhru->place($kind, $provider, $remoteServiceId, $order);
    }
}
