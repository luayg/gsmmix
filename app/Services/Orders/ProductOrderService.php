<?php

namespace App\Services\Orders;

use App\Models\LocalReply;
use App\Models\LocalSource;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductOrderService
{
    public function create(array $data, int $actorId): ProductOrder
    {
        return DB::transaction(function () use ($data, $actorId): ProductOrder {
            // Serializes retries for one customer, including before the order exists.
            $user = User::query()->lockForUpdate()->findOrFail($data['user_id']);
            $device = trim((string) ($data['device'] ?? ''));
            $existing = ProductOrder::query()->where('request_uid', $data['request_uid'])->first();
            if ($existing) {
                if ((int) $existing->user_id !== (int) $user->id
                    || (int) $existing->product_id !== (int) $data['product_id']
                    || (int) ($existing->request['actor_id'] ?? 0) !== $actorId
                    || (string) $existing->device !== $device) {
                    throw ValidationException::withMessages(['request_uid' => 'This submission was already used for another order.']);
                }
                return $existing;
            }
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            if ($user->status !== 'active' || !$product->active) {
                throw ValidationException::withMessages(['product_id' => 'Choose an active customer and an active product.']);
            }
            if ($product->device_based && $device === '') {
                throw ValidationException::withMessages(['device' => 'A device identifier is required for this product.']);
            }
            // The catalog defines price in account credits; converted_price is display metadata.
            $price = (string) $product->getRawOriginal('price');
            if (!is_numeric($price) || bccomp($price, '0', 4) < 0 || bccomp($price, '99999999.99', 4) > 0) {
                throw ValidationException::withMessages(['product_id' => 'Correct the product credit price before ordering.']);
            }
            $amount = bcadd($price, '0', 4);
            $balance = bcadd((string) ($user->getRawOriginal('balance') ?? 0), '0', 4);
            if (bccomp($balance, $amount, 4) < 0) {
                throw ValidationException::withMessages(['user_id' => 'The customer has insufficient credits.']);
            }
            $reply = null;
            if ($product->local_source_id) {
                // A source can be shared by several products; serialize stock allocation there.
                LocalSource::query()->lockForUpdate()->findOrFail($product->local_source_id);
                $reply = LocalReply::query()->where('local_source_id', $product->local_source_id)
                    ->where('device_based', (bool) $product->device_based)
                    ->when($product->device_based, fn ($query) => $query->where('device_identifier', $device))
                    ->whereNull('used_by_product_order_id')->whereNull('used_at')
                    ->whereDoesntHave('linkedProductOrders')
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->orderBy('id')->lockForUpdate()->first();
                if (!$reply || trim((string) $reply->reply) === '') {
                    throw ValidationException::withMessages(['product_id' => 'No available, unexpired reply matches this product and device.']);
                }
            }
            $newBalance = bcsub($balance, $amount, 4);
            $order = ProductOrder::create([
                'request_uid' => $data['request_uid'], 'product_id' => $product->id,
                'user_id' => $user->id, 'email' => $user->email,
                'local_source_id' => $product->local_source_id, 'local_reply_id' => $reply?->id,
                'status' => $reply ? 'success' : 'waiting', 'order_price' => $amount,
                'device' => $device, 'comments' => $data['comments'] ?? null,
                'response' => $reply?->reply, 'replied_at' => $reply ? now() : null,
                'request' => [
                    'pipeline' => 'local_product_v1', 'actor_id' => $actorId,
                    'product_name' => $product->name, 'quantity' => 1,
                    'charged_amount' => $amount, 'financial_state' => 'charged',
                    'balance_before' => $balance, 'balance_after' => $newBalance,
                    'charged_at' => now()->toDateTimeString(),
                ],
            ]);
            $user->balance = $newBalance;
            $user->save();
            if ($reply) {
                $reply->update(['used_by_product_order_id' => $order->id, 'used_at' => now()]);
            }
            return $order;
        }, 3);
    }

    public function update(int $id, array $data): ProductOrder
    {
        return DB::transaction(function () use ($id, $data): ProductOrder {
            $order = ProductOrder::query()->lockForUpdate()->findOrFail($id);
            $status = $data['status'];
            $oldStatus = $order->status;
            $metadata = $order->request ?? [];
            if (($metadata['pipeline'] ?? null) !== 'local_product_v1' && $status !== $oldStatus) {
                throw ValidationException::withMessages(['status' => 'Historical orders need a financial review before their status can change.']);
            }
            if ($oldStatus === 'success' && ($status !== 'success'
                || (array_key_exists('response', $data) && (string) $data['response'] !== (string) $order->response))) {
                throw ValidationException::withMessages(['status' => 'Delivered orders retain their result and charge. Use a separate reviewed financial adjustment if required.']);
            }
            if ($status === 'success' && $oldStatus !== 'success') {
                if ($order->local_source_id || trim((string) ($data['response'] ?? '')) === '') {
                    throw ValidationException::withMessages(['response' => 'Manual completion requires a manual-source order and a delivery result.']);
                }
            }
            if ($status !== $oldStatus) {
                try {
                    $finance = app(OrderFinanceService::class);
                    if (in_array($status, ['cancelled', 'rejected'], true)) {
                        $finance->refundOrderIfNeeded($order, 'product_' . $status);
                    } else {
                        $finance->rechargeOrderIfNeeded($order, 'product_' . $status);
                    }
                } catch (\RuntimeException $exception) {
                    if ($exception->getMessage() !== 'INSUFFICIENT_BALANCE_RECHARGE') {
                        throw $exception;
                    }
                    throw ValidationException::withMessages(['status' => 'The customer has insufficient credits to reactivate this order.']);
                }
                $metadata = $order->request;
                if (bccomp((string) ($metadata['charged_amount'] ?? '0'), '0', 4) === 0) {
                    $metadata['financial_state'] = in_array($status, ['cancelled', 'rejected'], true) ? 'refunded' : 'charged';
                    $order->request = $metadata;
                }
                $order->status = $status;
                $order->replied_at = in_array($status, ['success', 'cancelled', 'rejected'], true) ? now() : null;
            }
            if ($status === 'success' && $oldStatus !== 'success') {
                $order->response = trim($data['response']);
            }
            if (array_key_exists('comments', $data)) {
                $order->comments = $data['comments'];
            }
            $order->save();
            return $order;
        }, 3);
    }
}
