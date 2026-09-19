<?php

namespace App\Support;

use App\Models\FileOrder;
use App\Models\FinanceAccount;
use App\Models\ImeiOrder;
use App\Models\ProductOrder;
use App\Models\ServerOrder;
use App\Models\SmmOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class CustomerOverview
{
    private const ORDER_MODELS = [ImeiOrder::class, ServerOrder::class, FileOrder::class, SmmOrder::class, ProductOrder::class];

    public function lockedAmount(int $userId): float
    {
        $total = '0.0000';
        foreach (self::ORDER_MODELS as $model) {
            $instance = new $model;
            if (!Schema::hasTable($instance->getTable())) continue;
            foreach ($model::query()->where('user_id', $userId)->whereIn('status', ['waiting', 'inprogress'])->get() as $order) {
                $request = (array) ($order->request ?? []);
                $amount = $request['charged_amount'] ?? null;
                if ($amount === null && !empty($request['product_order_id'])) $amount = 0;
                if ($amount === null) $amount = $order instanceof ProductOrder ? $order->order_price : ($order->price ?? $order->order_price ?? 0);
                if (is_numeric($amount) && bccomp((string) $amount, '0', 4) === 1) $total = bcadd($total, (string) $amount, 4);
            }
        }
        return (float) $total;
    }

    public function totalReceipts(int $userId): float
    {
        if (!Schema::hasTable('finance_accounts')) return 0.0;
        return (float) (FinanceAccount::query()->where('user_id', $userId)->value('total_receipts') ?? 0);
    }

    public function serviceName(Model $order, string $type): string
    {
        $value = $type === 'product' ? $order->product?->name : $order->service?->name;
        if ($value instanceof \Illuminate\Support\Collection) $value = $value->all();
        if (is_array($value)) return trim((string) ($value[app()->getLocale()] ?? $value['en'] ?? $value['fallback'] ?? reset($value) ?: ucfirst($type).' order'));
        $decoded = is_string($value) ? json_decode($value, true) : null;
        if (is_array($decoded)) return trim((string) ($decoded[app()->getLocale()] ?? $decoded['en'] ?? $decoded['fallback'] ?? reset($decoded) ?: ucfirst($type).' order'));
        return trim((string) $value) ?: ucfirst($type).' order';
    }

    public function orderAmount(Model $order): float
    {
        $request = (array) ($order->request ?? []);
        if (array_key_exists('charged_amount', $request) && is_numeric($request['charged_amount'])) {
            return (float) $request['charged_amount'];
        }

        return (float) ($order instanceof ProductOrder ? ($order->order_price ?? 0) : ($order->price ?? $order->order_price ?? 0));
    }

    public function isProductLinkedServiceOrder(Model $order): bool
    {
        if ($order instanceof ProductOrder) {
            return false;
        }

        return (int) data_get($order->request, 'product_order_id', 0) > 0;
    }
}
