<?php

namespace App\Observers;

use App\Models\ServerOrder;
use App\Models\ServiceGroupPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ServerOrderQuantityBillingObserver
{
    public function creating(ServerOrder $order): void
    {
        $request = $order->request;
        if (!is_array($request)) {
            $request = is_string($request) ? (json_decode($request, true) ?: []) : [];
        }

        // The admin order pipeline writes request_uid and already debits one unit
        // inside a database transaction. Legacy/direct provider test endpoints do not.
        if (empty($request['request_uid']) || !empty($request['server_quantity_billing_applied'])) {
            return;
        }

        if (DB::transactionLevel() < 1) {
            throw new \RuntimeException('SERVER_QUANTITY_BILLING_REQUIRES_TRANSACTION');
        }

        $userId = (int)($order->user_id ?? 0);
        if ($userId <= 0) {
            throw new \RuntimeException('SERVER_QUANTITY_BILLING_USER_MISSING');
        }

        $quantity = max(1, (int)($order->quantity ?? 1));
        $parentUnitSell = $this->money($order->price ?? 0);
        $unitCost = $this->money($order->order_price ?? 0);

        $user = User::query()->lockForUpdate()->find($userId);
        if (!$user) {
            throw new \RuntimeException('SERVER_QUANTITY_BILLING_USER_MISSING');
        }

        $unitSell = $this->groupSellPrice($order, $user, $parentUnitSell);
        $totalSell = bcmul($unitSell, (string)$quantity, 2);
        $totalCost = bcmul($unitCost, (string)$quantity, 2);
        $totalProfit = bcsub($totalSell, $totalCost, 2);

        // BaseOrdersController has already charged parentUnitSell once. Adjust only
        // the difference here, keeping the whole operation in the same transaction.
        $adjustment = bcsub($totalSell, $parentUnitSell, 2);
        $balance = $this->money($user->balance ?? 0);

        if (bccomp($adjustment, '0.00', 2) === 1) {
            if (bccomp($balance, $adjustment, 2) === -1) {
                throw new \RuntimeException('INSUFFICIENT_BALANCE');
            }
            $user->balance = bcsub($balance, $adjustment, 2);
            $user->save();
        } elseif (bccomp($adjustment, '0.00', 2) === -1) {
            $user->balance = bcadd($balance, ltrim($adjustment, '-'), 2);
            $user->save();
        }

        $order->price = $totalSell;
        $order->order_price = $totalCost;
        $order->profit = $totalProfit;

        $request['charged_amount'] = (float)$totalSell;
        $request['unit_sell_price'] = (float)$unitSell;
        $request['unit_cost_price'] = (float)$unitCost;
        $request['billing_quantity'] = $quantity;
        $request['billing_mode'] = 'server_per_unit';
        $request['server_quantity_billing_applied'] = true;
        $order->request = $request;
    }

    private function groupSellPrice(ServerOrder $order, User $user, string $fallback): string
    {
        $groupId = (int)($user->group_id ?? 0);
        $serviceId = (int)($order->service_id ?? 0);

        if ($groupId <= 0 || $serviceId <= 0 || !Schema::hasTable('service_group_prices')) {
            return $fallback;
        }

        $groupPrice = ServiceGroupPrice::query()
            ->where('service_type', 'server')
            ->where('service_id', $serviceId)
            ->where('group_id', $groupId)
            ->first();

        $price = $groupPrice?->finalPrice($groupPrice->auto_price ? $order->service : null);
        if ($price === null) {
            return $fallback;
        }

        return $this->money(max(0, $price));
    }

    private function money(mixed $value): string
    {
        return is_numeric($value) ? number_format((float)$value, 2, '.', '') : '0.00';
    }
}
