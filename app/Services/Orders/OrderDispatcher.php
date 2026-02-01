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

        match ($kind) {
            'imei'   => $this->dispatchImei(ImeiOrder::findOrFail($orderId)),
            'server' => $this->dispatchServer(ServerOrder::findOrFail($orderId)),
            'file'   => $this->dispatchFile(FileOrder::findOrFail($orderId)),
            default  => Log::warning('Unknown order kind', ['kind' => $kind, 'order_id' => $orderId]),
        };
    }

    private function resolveProviderFromService($order): ?ApiProvider
    {
        $order->load(['service','provider']);

        if (!$order->service || !$order->service->supplier_id || !$order->service->remote_id) {
            $order->status = 'rejected';
            $order->response = ['ERROR' => [['MESSAGE' => 'Service is not linked to API provider.']]];
            $order->replied_at = now();
            $order->processing = false;
            $order->save();
            return null;
        }

        $provider = ApiProvider::find((int)$order->service->supplier_id);
        if (!$provider || (int)$provider->active !== 1) {
            $order->status = 'rejected';
            $order->response = ['ERROR' => [['MESSAGE' => 'Provider is inactive or missing.']]];
            $order->replied_at = now();
            $order->processing = false;
            $order->save();
            return null;
        }

        $order->supplier_id = $provider->id;
        return $provider;
    }

    public function dispatchImei(ImeiOrder $order): void
    {
        $provider = $this->resolveProviderFromService($order);
        if (!$provider) return;

        $order->status = 'inprogress';
        $order->processing = true;
        $order->save();

        try {
            $result = $this->sender->sendImei($provider, $order);

            $order->request  = $result['request']  ?? $order->request;
            $order->response = $result['response'] ?? $order->response;

            if (($result['ok'] ?? false) === true) {
                $order->remote_id = $result['remote_id'] ?? $order->remote_id;
                $order->status    = $result['status'] ?? 'inprogress';
            } else {
                $order->status     = $result['status'] ?? 'rejected';
                $order->replied_at = now();
            }

            $order->processing = false;
            $order->save();

        } catch (\Throwable $e) {
            Log::error('dispatchImei failed', ['order_id'=>$order->id,'err'=>$e->getMessage()]);
            $order->status = 'rejected';
            $order->processing = false;
            $order->response = ['ERROR' => [['MESSAGE' => 'Dispatch error: '.$e->getMessage()]]];
            $order->replied_at = now();
            $order->save();
        }
    }

    public function dispatchServer(ServerOrder $order): void
    {
        $provider = $this->resolveProviderFromService($order);
        if (!$provider) return;

        $order->status = 'inprogress';
        $order->processing = true;
        $order->save();

        try {
            $result = $this->sender->sendServer($provider, $order);

            $order->request  = $result['request']  ?? $order->request;
            $order->response = $result['response'] ?? $order->response;

            if (($result['ok'] ?? false) === true) {
                $order->remote_id = $result['remote_id'] ?? $order->remote_id;
                $order->status    = $result['status'] ?? 'inprogress';
            } else {
                $order->status     = $result['status'] ?? 'rejected';
                $order->replied_at = now();
            }

            $order->processing = false;
            $order->save();

        } catch (\Throwable $e) {
            Log::error('dispatchServer failed', ['order_id'=>$order->id,'err'=>$e->getMessage()]);
            $order->status = 'rejected';
            $order->processing = false;
            $order->response = ['ERROR' => [['MESSAGE' => 'Dispatch error: '.$e->getMessage()]]];
            $order->replied_at = now();
            $order->save();
        }
    }

    public function dispatchFile(FileOrder $order): void
    {
        $provider = $this->resolveProviderFromService($order);
        if (!$provider) return;

        $order->status = 'inprogress';
        $order->processing = true;
        $order->save();

        try {
            $result = $this->sender->sendFile($provider, $order);

            $order->request  = $result['request']  ?? $order->request;
            $order->response = $result['response'] ?? $order->response;

            if (($result['ok'] ?? false) === true) {
                $order->remote_id = $result['remote_id'] ?? $order->remote_id;
                $order->status    = $result['status'] ?? 'inprogress';
            } else {
                $order->status     = $result['status'] ?? 'rejected';
                $order->replied_at = now();
            }

            $order->processing = false;
            $order->save();

        } catch (\Throwable $e) {
            Log::error('dispatchFile failed', ['order_id'=>$order->id,'err'=>$e->getMessage()]);
            $order->status = 'rejected';
            $order->processing = false;
            $order->response = ['ERROR' => [['MESSAGE' => 'Dispatch error: '.$e->getMessage()]]];
            $order->replied_at = now();
            $order->save();
        }
    }
}
