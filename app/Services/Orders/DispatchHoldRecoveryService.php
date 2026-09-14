<?php

namespace App\Services\Orders;

use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use App\Models\SmmOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class DispatchHoldRecoveryService
{
    /** @return class-string<Model> */
    private function modelFor(string $kind): string
    {
        return match (strtolower(trim($kind))) {
            'imei' => ImeiOrder::class,
            'server' => ServerOrder::class,
            'file' => FileOrder::class,
            'smm' => SmmOrder::class,
            default => throw new \InvalidArgumentException('UNSUPPORTED_ORDER_KIND'),
        };
    }

    public function resolve(string $kind, int $orderId, string $remoteId, ?string $note = null): Model
    {
        $remoteId = trim($remoteId);
        if ($remoteId === '') {
            throw new \InvalidArgumentException('REMOTE_ID_REQUIRED');
        }

        $model = $this->modelFor($kind);

        return DB::transaction(function () use ($model, $orderId, $remoteId, $note): Model {
            /** @var Model $order */
            $order = $model::query()->lockForUpdate()->findOrFail($orderId);

            if (!(bool)($order->api_order ?? false)) {
                throw new \RuntimeException('DISPATCH_HOLD_NOT_API_ORDER');
            }

            if (trim((string)($order->remote_id ?? '')) !== '') {
                throw new \RuntimeException('DISPATCH_HOLD_REMOTE_ID_ALREADY_SET');
            }

            if ((bool)($order->processing ?? false)) {
                throw new \RuntimeException('DISPATCH_HOLD_STILL_PROCESSING');
            }

            if (strtolower(trim((string)($order->status ?? ''))) !== 'waiting') {
                throw new \RuntimeException('DISPATCH_HOLD_NOT_WAITING');
            }

            $request = $this->requestMeta($order);
            if (!$this->hasHold($request)) {
                throw new \RuntimeException('DISPATCH_HOLD_NOT_FOUND');
            }

            if ($this->financialState($request) === 'refunded') {
                // Resolving a refunded order would reactivate a remotely accepted order
                // without restoring its charge. Require separate financial review first.
                throw new \RuntimeException('DISPATCH_HOLD_ORDER_REFUNDED');
            }

            $resolvedAt = now()->toDateTimeString();

            if (array_key_exists('dispatch_hold', $request)) {
                $request['dispatch_hold'] = false;
            }

            $nested = $request['request'] ?? null;
            if (is_array($nested)) {
                $nested['dispatch_hold'] = false;
                $nested['dispatch_hold_resolved_at'] = $resolvedAt;
                $nested['dispatch_hold_resolved_remote_id'] = $remoteId;
                $request['request'] = $nested;
            }

            $request['dispatch_hold_resolved_at'] = $resolvedAt;
            $request['dispatch_hold_resolved_remote_id'] = $remoteId;
            if ($note !== null && trim($note) !== '') {
                $request['dispatch_hold_resolution_note'] = trim($note);
            }

            $response = $order->response;
            if (is_string($response)) {
                $decoded = json_decode($response, true);
                $response = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($response)) {
                $response = [];
            }
            $response['type'] = 'info';
            $response['message'] = 'Dispatch hold resolved with provider order ID. Awaiting provider status sync.';
            $response['reference_id'] = $remoteId;

            $order->remote_id = $remoteId;
            $order->status = 'inprogress';
            $order->processing = true;
            $order->request = $request;
            $order->response = $response;
            $order->save();

            return $order->fresh();
        });
    }

    public function hasHold(array $request): bool
    {
        return !empty($request['dispatch_hold']) || !empty(data_get($request, 'request.dispatch_hold'));
    }

    public function requestMeta(Model $order): array
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

    public function financialState(array $request): string
    {
        $state = strtolower(trim((string)($request['financial_state'] ?? '')));
        if (in_array($state, ['charged', 'refunded'], true)) {
            return $state;
        }

        return !empty($request['refunded_at']) && empty($request['recharged_at'])
            ? 'refunded'
            : 'charged';
    }
}
