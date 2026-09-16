<?php

namespace App\Services\Orders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OrderDispatchClaimService
{
    /**
     * Atomically claim one waiting API order before any provider request is made.
     * A second worker trying to claim the same row will receive null.
     *
     * @param class-string<Model> $modelClass
     */
    public function claim(string $modelClass, int $orderId): ?Model
    {
        return DB::transaction(function () use ($modelClass, $orderId): ?Model {
            /** @var Model|null $order */
            $order = $modelClass::query()->lockForUpdate()->find($orderId);
            if (!$order) {
                return null;
            }

            if (!(bool)($order->api_order ?? false)) {
                return null;
            }

            if (strtolower(trim((string)($order->status ?? ''))) !== 'waiting') {
                return null;
            }

            if ((bool)($order->processing ?? false)) {
                return null;
            }

            if (trim((string)($order->remote_id ?? '')) !== '') {
                return null;
            }

            $serviceRelation = $order->service();
            if (Schema::hasTable($serviceRelation->getRelated()->getTable())) {
                $order->loadMissing('service');
                if ((bool)($order->service?->needs_approval ?? false) && !(bool)($order->approved ?? false)) {
                    return null;
                }
            }

            $request = $this->requestMeta($order);
            if (!empty($request['dispatch_hold']) || !empty(data_get($request, 'request.dispatch_hold'))) {
                return null;
            }

            $request['dispatch_claimed_at'] = now()->toDateTimeString();
            $request['dispatch_attempt'] = ((int)($request['dispatch_attempt'] ?? 0)) + 1;

            $order->processing = true;
            $order->status = 'inprogress';
            $order->request = $request;
            $order->save();

            return $order;
        });
    }

    /**
     * Release only a still-unresolved claim after an unexpected local failure.
     * Provider/network failures normally update the order inside OrderDispatcher.
     *
     * @param class-string<Model> $modelClass
     */
    public function releaseUnexpectedFailure(string $modelClass, int $orderId): void
    {
        DB::transaction(function () use ($modelClass, $orderId): void {
            /** @var Model|null $order */
            $order = $modelClass::query()->lockForUpdate()->find($orderId);
            if (!$order) {
                return;
            }

            if (trim((string)($order->remote_id ?? '')) !== '') {
                return;
            }

            $status = strtolower(trim((string)($order->status ?? '')));
            if (!(bool)($order->processing ?? false) || !in_array($status, ['waiting', 'inprogress'], true)) {
                return;
            }

            $request = $this->requestMeta($order);
            $request['dispatch_failed_at'] = now()->toDateTimeString();
            $request['dispatch_error'] = 'Unexpected local dispatch failure; retry allowed.';

            $order->processing = false;
            $order->status = 'waiting';
            $order->request = $request;
            $order->save();
        });
    }

    private function requestMeta(Model $order): array
    {
        $request = $order->request ?? [];
        if (is_array($request)) {
            return $request;
        }

        if (is_string($request)) {
            $decoded = json_decode($request, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
