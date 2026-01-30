<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Services\Providers\DhruClient;

class OrderDispatcher
{
    public function sendByProvider(ApiProvider $provider, int $serviceRemoteId, array $payload): array
    {
        $type = strtolower((string)$provider->type);

        return match ($type) {
            'dhru' => (new DhruClient($provider))->placeOrder($serviceRemoteId, $payload),
            default => ['ok' => false, 'error' => "Provider type [$type] not supported yet."],
        };
    }

    public function refreshByProvider(ApiProvider $provider, int $remoteOrderId): array
    {
        $type = strtolower((string)$provider->type);

        return match ($type) {
            'dhru' => (new DhruClient($provider))->getOrder($remoteOrderId),
            default => ['ok' => false, 'error' => "Provider type [$type] not supported yet."],
        };
    }

    /**
     * نحاول نحدد اسم الحقل المرسل حسب label في main_field
     * مثال: IMEI / SN / EMAIL / UDID ...
     */
    public function buildMainFieldPayload(string $mainLabel, string $value): array
    {
        $label = strtoupper(trim($mainLabel));
        $label = preg_replace('/\s+/', '_', $label);

        // بعض التطبيع
        $map = [
            'SERIAL' => 'SN',
            'SERIAL_NUMBER' => 'SN',
            'E-MAIL' => 'EMAIL',
        ];
        $key = $map[$label] ?? $label;

        // fallback إن كان فاضي
        if ($key === '' || $key === 'GROUP') $key = 'IMEI';

        return [$key => $value];
    }
}
