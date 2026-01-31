<?php

namespace App\Services\Orders;

use App\Models\ImeiOrder;
use App\Models\ApiProvider;
use Illuminate\Support\Facades\Log;

class OrderDispatcher
{
    public function __construct(
        private OrderSender $sender
    ) {}

    public function dispatchImei(ImeiOrder $order): void
    {
        $order->load(['service','provider']);

        // لازم يكون مربوط بمزود + remote_id للخدمة
        if (!$order->service || !$order->service->supplier_id || !$order->service->remote_id) {
            $order->status = 'rejected';
            $order->response = 'Service is not linked to API provider.';
            $order->replied_at = now();
            $order->save();
            return;
        }

        $provider = ApiProvider::find($order->service->supplier_id);
        if (!$provider || (int)$provider->active !== 1) {
            $order->status = 'rejected';
            $order->response = 'Provider is inactive or missing.';
            $order->replied_at = now();
            $order->save();
            return;
        }

        // waiting -> inprogress
        $order->supplier_id = $provider->id;
        $order->status = 'inprogress';
        $order->processing = true;
        $order->save();

        try {
            $result = $this->sender->sendImei($provider, $order);

            $order->request = $result['request'] ?? null;
            $order->response = $result['response'] ?? null;

            if (($result['ok'] ?? false) === true) {
                $order->remote_id = $result['remote_id'] ?? $order->remote_id;
                // مزودات Dhru عادة ترجع REFERENCEID = تم استلام الطلب => inprogress
                $order->status = $result['status'] ?? 'inprogress';
            } else {
                $order->status = $result['status'] ?? 'rejected';
                $order->replied_at = now();
            }

            $order->processing = false;
            $order->save();

        } catch (\Throwable $e) {
            Log::error('dispatchImei failed', ['order_id'=>$order->id,'err'=>$e->getMessage()]);
            $order->status = 'rejected';
            $order->processing = false;
            $order->response = 'Dispatch error: '.$e->getMessage();
            $order->replied_at = now();
            $order->save();
        }
    }
}
