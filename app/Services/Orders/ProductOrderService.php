<?php

namespace App\Services\Orders;

use App\Models\ApiProvider;
use App\Models\LocalReply;
use App\Models\LocalSource;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ServiceGroupPrice;
use App\Models\User;
use App\Support\ProductService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductOrderService
{
    public function create(array $data, int $actorId): ProductOrder
    {
        $dispatch = null;
        $order = DB::transaction(function () use ($data, $actorId, &$dispatch): ProductOrder {
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

            $sourceType = strtolower(trim((string) ($product->source_type ?: 'manual')));
            if (!in_array($sourceType, ['manual', 'service', 'local_source'], true)) {
                throw ValidationException::withMessages(['product_id' => 'The product source type is not supported.']);
            }
            $serviceType = null;
            $service = null;
            if ($sourceType === 'service') {
                $serviceType = strtolower(trim((string) $product->service_type));
                $service = ProductService::find($serviceType, (int) $product->service_id, true);
                if (!$service) {
                    throw ValidationException::withMessages(['product_id' => 'The product linked service is missing or inactive.']);
                }
                $this->validateServiceInputs($serviceType, $service, $data);
            }
            if (($product->device_based || $serviceType === 'imei') && $device === '') {
                throw ValidationException::withMessages(['device' => 'A device identifier is required for this product.']);
            }
            if ($sourceType === 'service' && $serviceType === 'file' && !($data['file'] ?? null) instanceof UploadedFile) {
                throw ValidationException::withMessages(['file' => 'Choose a file for this product.']);
            }
            // The catalog defines price in account credits; converted_price is display metadata.
            $price = (string) $product->getRawOriginal('price');
            if ($user->group_id && Schema::hasTable('service_group_prices')) {
                $groupPrice = ServiceGroupPrice::query()->where('service_type', 'product')
                    ->where('service_id', $product->id)->where('group_id', $user->group_id)->first();
                if ($groupPrice) {
                    $price = number_format((float) $groupPrice->finalPrice($product), 4, '.', '');
                }
            }
            if (!is_numeric($price) || bccomp($price, '0', 4) < 0 || bccomp($price, '99999999.99', 4) > 0) {
                throw ValidationException::withMessages(['product_id' => 'Correct the product credit price before ordering.']);
            }
            $amount = bcadd($price, '0', 4);
            $balance = bcadd((string) ($user->getRawOriginal('balance') ?? 0), '0', 4);
            if (bccomp($balance, $amount, 4) < 0) {
                throw ValidationException::withMessages(['user_id' => 'The customer has insufficient credits.']);
            }
            $reply = null;
            if ($sourceType === 'local_source') {
                if (!$product->local_source_id) {
                    throw ValidationException::withMessages(['product_id' => 'Choose a local source for this product.']);
                }
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
                'local_source_id' => $sourceType === 'local_source' ? $product->local_source_id : null,
                'local_reply_id' => $reply?->id,
                'status' => $reply ? 'success' : 'waiting', 'order_price' => $amount,
                'device' => $device, 'comments' => $data['comments'] ?? null,
                'response' => $reply?->reply, 'replied_at' => $reply ? now() : null,
                'request' => [
                    'pipeline' => $sourceType . '_product_v1', 'actor_id' => $actorId,
                    'product_name' => $product->name, 'quantity' => 1,
                    'source_type' => $sourceType,
                    'service_type' => $serviceType, 'service_id' => $service?->id,
                    'charged_amount' => $amount, 'financial_state' => 'charged',
                    'balance_before' => $balance, 'balance_after' => $newBalance,
                    'charged_at' => now()->toDateTimeString(),
                ],
            ]);
            $user->balance = $newBalance;
            $user->save();

            if ($sourceType === 'service') {
                [$serviceOrder, $shouldDispatch] = $this->createLinkedServiceOrder($order, $product, $service, $data);
                $order->service_order_type = $serviceType;
                $order->service_order_id = $serviceOrder->getKey();
                $metadata = (array) $order->request;
                $metadata['service_order_id'] = (int) $serviceOrder->getKey();
                $order->request = $metadata;
                $order->save();
                if ($shouldDispatch) {
                    $dispatch = [$serviceType, (int) $serviceOrder->getKey()];
                }
            } elseif ($reply) {
                $reply->update(['used_by_product_order_id' => $order->id, 'used_at' => now()]);
            }
            return $order;
        }, 3);

        if ($dispatch) {
            try {
                app(OrderDispatcher::class)->send($dispatch[0], $dispatch[1]);
            } catch (\Throwable $exception) {
                Log::error('Product-linked service dispatch failed', [
                    'product_order_id' => $order->id,
                    'service_order_type' => $dispatch[0],
                    'service_order_id' => $dispatch[1],
                    'error' => $exception->getMessage(),
                ]);
            }
            $this->syncFromLinkedOrder($order);
        }

        return $order->fresh();
    }

    private function validateServiceInputs(string $type, Model $service, array $data): void
    {
        $schema = ProductService::inputSchema($type, $service);
        $errors = [];
        if ($schema['main']) {
            $value = trim((string)($data['device'] ?? ''));
            $field = $schema['main'];
            if ($value === '') $errors['device'] = $field['name'].' is required.';
            elseif (($field['type'] ?? '') === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) $errors['device'] = 'Enter a valid email address.';
            elseif (($field['type'] ?? '') === 'number' && !preg_match('/^\d+$/', $value)) $errors['device'] = $field['name'].' must contain numbers only.';
            elseif (!empty($field['minimum']) && mb_strlen($value) < $field['minimum']) $errors['device'] = $field['name'].' must be at least '.$field['minimum'].' characters.';
            elseif (!empty($field['maximum']) && mb_strlen($value) > $field['maximum']) $errors['device'] = $field['name'].' may not exceed '.$field['maximum'].' characters.';
        }
        $submitted = is_array($data['required'] ?? null) ? $data['required'] : [];
        foreach ($schema['fields'] as $field) {
            $value = trim((string)($submitted[$field['input']] ?? ''));
            $key = 'required.'.$field['input'];
            if ($field['required'] && $value === '') {$errors[$key] = $field['name'].' is required.'; continue;}
            if ($value === '') continue;
            if ($field['type'] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[$key] = 'Enter a valid email address.';
            elseif ($field['minimum'] && mb_strlen($value) < $field['minimum']) $errors[$key] = $field['name'].' must be at least '.$field['minimum'].' characters.';
            elseif ($field['maximum'] && mb_strlen($value) > $field['maximum']) $errors[$key] = $field['name'].' may not exceed '.$field['maximum'].' characters.';
            elseif (in_array($field['type'], ['dropdown','select','radio'], true) && $field['options']) {
                $allowed = collect($field['options'])->map(fn($option) => (string)(is_array($option) ? ($option['value'] ?? $option['name'] ?? '') : $option))->all();
                if (!in_array($value, $allowed, true)) $errors[$key] = 'Choose a valid '.$field['name'].'.';
            }
        }
        if ($errors) throw ValidationException::withMessages($errors);
    }

    private function createLinkedServiceOrder(
        ProductOrder $productOrder,
        Product $product,
        Model $service,
        array $data
    ): array {
        $type = (string) $product->service_type;
        $orderModel = ProductService::orderModel($type);
        $provider = $service->supplier_id ? ApiProvider::find((int) $service->supplier_id) : null;
        $hasRemote = trim((string) $service->remote_id) !== '';
        $isApi = (int) ($service->source ?? 0) === 2 || (int) ($service->supplier_id ?? 0) > 0 || $hasRemote;
        $shouldDispatch = $isApi && $provider && (int) $provider->active === 1
            && $hasRemote && !(bool) ($service->needs_approval ?? false);
        $quantity = in_array($type, ['server', 'smm'], true) ? max(1, (int) ($data['quantity'] ?? 1)) : 1;
        $sellPrice = (string) $productOrder->order_price;
        $cost = number_format(max(0, (float) ($service->cost ?? 0)) * $quantity, 4, '.', '');

        /** @var Model $serviceOrder */
        $serviceOrder = new $orderModel();
        $serviceOrder->forceFill([
            'device' => trim((string) ($data['device'] ?? '')),
            'status' => $shouldDispatch ? 'inprogress' : 'waiting',
            'processing' => $shouldDispatch,
            'api_order' => $isApi,
            'price' => $sellPrice,
            'order_price' => $cost,
            'profit' => number_format((float) $sellPrice - (float) $cost, 4, '.', ''),
            'user_id' => $productOrder->user_id,
            'email' => $productOrder->email,
            'service_id' => $service->getKey(),
            'supplier_id' => $provider?->id,
            'comments' => $data['comments'] ?? null,
            'params' => [
                'kind' => $type,
                'quantity' => $quantity,
                'fields' => is_array($data['required'] ?? null) ? $data['required'] : [],
                'product_order_id' => $productOrder->id,
            ],
            // ProductOrder owns the customer charge and refund. The generated
            // service order must never debit or refund the customer a second time.
            'request' => [
                'charged_amount' => 0,
                'financial_state' => 'charged',
                'product_order_id' => $productOrder->id,
                'product_request_uid' => $productOrder->request_uid,
            ],
            'ip' => $data['ip'] ?? null,
        ]);
        if (in_array($type, ['server', 'smm'], true)) {
            $serviceOrder->quantity = $quantity;
        }
        if ($type === 'file') {
            /** @var UploadedFile $file */
            $file = $data['file'];
            $serviceOrder->device = $file->getClientOriginalName();
            $serviceOrder->storage_path = $file->store('orders/files');
        }
        $serviceOrder->save();

        return [$serviceOrder, (bool) $shouldDispatch];
    }

    private function syncFromLinkedOrder(ProductOrder $productOrder): void
    {
        $model = ProductService::orderModel((string) $productOrder->service_order_type);
        $linked = $model::find($productOrder->service_order_id);
        if (!$linked) {
            return;
        }
        $productOrder->status = (string) $linked->status;
        $productOrder->response = is_array($linked->response)
            ? json_encode($linked->response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $linked->response;
        $metadata = (array) $productOrder->request;
        $linkedRequest = (array) ($linked->request ?? []);
        unset($metadata['internal_dispatch_note'], $metadata['internal_provider_note']);
        if (!empty($linkedRequest['internal_dispatch_note'])) {
            $metadata['internal_dispatch_note'] = $linkedRequest['internal_dispatch_note'];
        }
        if (!empty($linkedRequest['internal_provider_note'])) {
            $metadata['internal_provider_note'] = $linkedRequest['internal_provider_note'];
        }
        $productOrder->request = $metadata;
        $productOrder->replied_at = $linked->replied_at;
        $productOrder->save();
        if (in_array($productOrder->status, ['rejected', 'cancelled'], true)) {
            app(OrderFinanceService::class)->refundOrderIfNeeded($productOrder, 'linked_service_' . $productOrder->status);
        }
    }

    public function syncFromServiceOrder(Model $serviceOrder): void
    {
        $productOrderId = (int) data_get($serviceOrder->request, 'product_order_id', 0);
        if ($productOrderId <= 0) {
            return;
        }

        $productOrder = ProductOrder::find($productOrderId);
        if (!$productOrder) {
            return;
        }

        $this->syncFromLinkedOrder($productOrder);
    }

    public function update(int $id, array $data): ProductOrder
    {
        return DB::transaction(function () use ($id, $data): ProductOrder {
            $order = ProductOrder::query()->lockForUpdate()->findOrFail($id);
            $status = $data['status'];
            $oldStatus = $order->status;
            $metadata = $order->request ?? [];
            if (!in_array(($metadata['pipeline'] ?? null), ['manual_product_v1', 'local_source_product_v1', 'service_product_v1'], true)
                && $status !== $oldStatus) {
                throw ValidationException::withMessages(['status' => 'Historical orders need a financial review before their status can change.']);
            }
            if ($oldStatus === 'success' && $status !== 'success') {
                throw ValidationException::withMessages(['status' => 'Delivered orders retain their result and charge. Use a separate reviewed financial adjustment if required.']);
            }
            if ($status === 'success' && $oldStatus !== 'success' && ($metadata['source_type'] ?? null) === 'manual') {
                $deliveryResult = trim((string) ($data['provider_reply_html'] ?? $data['response'] ?? ''));
                if ($deliveryResult === '') {
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
            if (array_key_exists('provider_reply_html', $data)) {
                $response = $order->response;
                if (is_string($response)) {
                    $decoded = json_decode($response, true);
                    $response = is_array($decoded) ? $decoded : ['result_text' => $response];
                }
                if (!is_array($response)) $response = [];
                $response['provider_reply_html'] = (string) ($data['provider_reply_html'] ?? '');
                $response['provider_reply_updated_at'] = now()->toDateTimeString();
                $order->response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (array_key_exists('response', $data) && trim((string) $data['response']) !== '') {
                $order->response = trim((string) $data['response']);
            }
            if (array_key_exists('comments', $data)) {
                $order->comments = $data['comments'];
            }
            $order->save();
            if ($order->service_order_type && $order->service_order_id) {
                $linkedModel = ProductService::orderModel((string) $order->service_order_type);
                $linked = $linkedModel::query()->lockForUpdate()->find($order->service_order_id);
                if ($linked) {
                    $linked->status = $order->status;
                    $linked->comments = $order->comments;
                    $linkedResponse = json_decode((string) $order->response, true);
                    $linked->response = is_array($linkedResponse) ? $linkedResponse : ['result_text' => (string) $order->response];
                    $linked->processing = $order->status === 'inprogress';
                    $linked->replied_at = in_array($order->status, ['success', 'rejected', 'cancelled'], true) ? ($order->replied_at ?: now()) : null;
                    $linked->save();
                }
            }
            return $order;
        }, 3);
    }
}
