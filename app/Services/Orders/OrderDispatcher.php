<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use Illuminate\Support\Facades\Log;

class OrderDispatcher
{
    public function __construct(
        private OrderSender $sender
    ) {}

    public function send(string $kind, int $orderId): void
    {
        $kind = strtolower(trim($kind));

        try {
            match ($kind) {
                'imei'   => $this->dispatchImei(ImeiOrder::findOrFail($orderId)),
                'server' => $this->dispatchServer(ServerOrder::findOrFail($orderId)),
                'file'   => $this->dispatchFile(FileOrder::findOrFail($orderId)),
                default  => Log::warning('Unknown order kind', ['kind'=>$kind,'order_id'=>$orderId]),
            };
        } catch (\Throwable $e) {
            Log::error('OrderDispatcher send failed', ['kind'=>$kind,'order_id'=>$orderId,'err'=>$e->getMessage()]);
        }
    }

    private function resolveProvider($order): ?ApiProvider
    {
        $order->load(['service','provider']);

        if (!$order->service || !$order->service->supplier_id || !$order->service->remote_id) {
            $order->status = 'rejected';
            $order->response = ['type'=>'error','message'=>'Service is not linked to API provider.'];
            $order->replied_at = now();
            $order->processing = false;
            $order->save();
            return null;
        }

        $provider = ApiProvider::find((int)$order->service->supplier_id);
        if (!$provider) {
            $order->status = 'rejected';
            $order->response = ['type'=>'error','message'=>'Provider missing.'];
            $order->replied_at = now();
            $order->processing = false;
            $order->save();
            return null;
        }

        // ✅ حتى لو provider inactive لا نرفض (حسب طلبك)، نخليها waiting
        if ((int)$provider->active !== 1) {
            $order->status = 'waiting';
            $order->processing = false;
            $order->response = ['type'=>'queued','message'=>'Provider disabled, waiting until enabled.'];
            $order->save();
            return null;
        }

        $order->supplier_id = $provider->id;
        return $provider;
    }

    private function saveGatewayResult($order, array $result): void
    {
        // نخزن raw للتتبع
        $order->request = [
            'request' => $result['request'] ?? null,
            'response_raw' => $result['response_raw'] ?? null,
        ];

        // نخزن رد واجهة مختصر
        $order->response = $result['response_ui'] ?? null;
    }

    private function applyResult($order, array $result): void
    {
        $this->saveGatewayResult($order, $result);

        $retryable = (bool)($result['retryable'] ?? false);

        if ($retryable) {
            // ✅ مطلوبك: يبقى waiting وينتظر
            $order->status = 'waiting';
            $order->processing = false;
            $order->save();
            return;
        }

        if (($result['ok'] ?? false) === true) {
            $order->remote_id = $result['remote_id'] ?? $order->remote_id;
            $order->status    = $result['status'] ?? 'inprogress';
            $order->processing = false;
            $order->save();
            return;
        }

        // non-retryable error
        $order->status = $result['status'] ?? 'rejected';
        $order->processing = false;
        $order->replied_at = now();
        $order->save();
    }

    public function dispatchImei(ImeiOrder $order): void
    {
        $provider = $this->resolveProvider($order);
        if (!$provider) return;

        $order->status = 'inprogress';
        $order->processing = true;
        $order->save();

        try {
            $result = $this->sender->sendImei($provider, $order);
            $this->applyResult($order, $result);
        } catch (\Throwable $e) {
            Log::error('dispatchImei failed', ['order_id'=>$order->id,'err'=>$e->getMessage()]);
            $order->status = 'waiting';            // ✅ لا نرفض، نخليه waiting لإعادة المحاولة
            $order->processing = false;
            $order->response = ['type'=>'queued','message'=>'Dispatch error, will retry: '.$e->getMessage()];
            $order->save();
        }
    }

    public function dispatchServer(ServerOrder $order): void
    {
        $provider = $this->resolveProvider($order);
        if (!$provider) return;

        $order->status = 'inprogress';
        $order->processing = true;
        $order->save();

        try {
            $result = $this->sender->sendServer($provider, $order);
            $this->applyResult($order, $result);
        } catch (\Throwable $e) {
            Log::error('dispatchServer failed', ['order_id'=>$order->id,'err'=>$e->getMessage()]);
            $order->status = 'waiting';
            $order->processing = false;
            $order->response = ['type'=>'queued','message'=>'Dispatch error, will retry: '.$e->getMessage()];
            $order->save();
        }
    }

    public function dispatchFile(FileOrder $order): void
    {
        $provider = $this->resolveProvider($order);
        if (!$provider) return;

        $order->status = 'inprogress';
        $order->processing = true;
        $order->save();

        try {
            $result = $this->sender->sendFile($provider, $order);
            $this->applyResult($order, $result);
        } catch (\Throwable $e) {
            Log::error('dispatchFile failed', ['order_id'=>$order->id,'err'=>$e->getMessage()]);
            $order->status = 'waiting';
            $order->processing = false;
            $order->response = ['type'=>'queued','message'=>'Dispatch error, will retry: '.$e->getMessage()];
            $order->save();
        }
    }
}
