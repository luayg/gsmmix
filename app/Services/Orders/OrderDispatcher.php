<?php

namespace App\Services\Orders;

use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use App\Models\FileOrder;
use Illuminate\Support\Facades\Log;

class OrderDispatcher
{
    public function __construct(private OrderSender $sender)
    {}

    public function dispatchNow(string $kind, int $orderId): void
    {
        $order = match ($kind) {
            'imei'   => ImeiOrder::query()->with(['service','provider'])->find($orderId),
            'server' => ServerOrder::query()->with(['service','provider'])->find($orderId),
            'file'   => FileOrder::query()->with(['service','provider'])->find($orderId),
            default  => null,
        };

        if (!$order) return;

        // فقط إذا API order
        if ((int)($order->api_order ?? 0) !== 1) return;

        // منع إعادة الإرسال لو هو بالفعل inprogress/success...
        if (in_array(($order->status ?? ''), ['inprogress','success'], true)) return;

        try {
            $order->processing = 1;
            $order->save();

            $result = $this->sender->send($kind, $order);

            // خزّن الطلب/الرد خام كما هو
            $order->request  = $result['request'] ?? null;
            $order->response = $result['response_raw'] ?? ($result['response'] ?? null);

            // remote id/reference
            if (!empty($result['remote_id'])) {
                $order->remote_id = (string)$result['remote_id'];
            }

            // status mapping
            $order->status = $result['status'] ?? 'inprogress';

            if (in_array($order->status, ['success','rejected','cancelled'], true)) {
                $order->replied_at = now();
            }

            $order->processing = 0;
            $order->save();

        } catch (\Throwable $e) {
            Log::error('Dispatch failed', [
                'kind' => $kind,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            $order->processing = 0;
            $order->status = 'rejected';
            $order->response = 'Dispatch error: '.$e->getMessage();
            $order->replied_at = now();
            $order->save();
        }
    }
}
