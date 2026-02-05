<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

    private function refundIfNeeded($order, string $reason): void
    {
        $req = (array)($order->request ?? []);
        if (!empty($req['refunded_at'])) return;

        $uid = (int)($order->user_id ?? 0);
        if ($uid <= 0) return;

        $amount = (float)($req['charged_amount'] ?? 0);
        if ($amount <= 0) return;

        DB::transaction(function () use ($order, $uid, $amount, $reason, $req) {
            $u = User::query()->lockForUpdate()->find($uid);
            if (!$u) return;

            $u->balance = (float)($u->balance ?? 0) + $amount;
            $u->save();

            $req['refunded_at'] = now()->toDateTimeString();
            $req['refunded_amount'] = $amount;
            $req['refunded_reason'] = $reason;

            $order->request = $req;
            $order->save();
        });
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

            $this->refundIfNeeded($order, 'dispatch_rejected_service_not_linked');
            return null;
        }

        $provider = ApiProvider::find((int)$order->service->supplier_id);
        if (!$provider) {
            $order->status = 'rejected';
            $order->response = ['type'=>'error','message'=>'Provider missing.'];
            $order->replied_at = now();
            $order->processing = false;
            $order->save();

            $this->refundIfNeeded($order, 'dispatch_rejected_provider_missing');
            return null;
        }

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
        $order->request = array_merge((array)$order->request, [
            'request' => $result['request'] ?? null,
            'response_raw' => $result['response_raw'] ?? null,
        ]);

        $order->response = $result['response_ui'] ?? null;
    }

    private function applyResult($order, array $result): void
    {
        $this->saveGatewayResult($order, $result);

        $retryable = (bool)($result['retryable'] ?? false);

        if ($retryable) {
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

        $order->status = $result['status'] ?? 'rejected';
        $order->processing = false;
        $order->replied_at = now();
        $order->save();

        // ✅ Refund فقط عند Rejected / Cancelled
       $st = strtolower(trim((string)$order->status));
        if (in_array($st, ['rejected','reject','cancelled','canceled'], true)) {
        $this->refundIfNeeded($order, 'dispatch_'.$st);
}

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
            $order->status = 'waiting';
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
