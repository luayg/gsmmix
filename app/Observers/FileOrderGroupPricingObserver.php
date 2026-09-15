<?php

namespace App\Observers;

use App\Models\FileOrder;
use App\Models\ServiceGroupPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FileOrderGroupPricingObserver
{
    public function creating(FileOrder $order): void
    {
        $request = $order->request;
        if (!is_array($request)) {
            $request = is_string($request) ? (json_decode($request, true) ?: []) : [];
        }

        // Only adjust orders created through the guarded admin pipeline.
        if (empty($request['request_uid']) || !empty($request['file_group_pricing_applied'])) {
            return;
        }

        if (DB::transactionLevel() < 1) {
            throw new \RuntimeException('FILE_GROUP_PRICING_REQUIRES_TRANSACTION');
        }

        $userId = (int)($order->user_id ?? 0);
        $serviceId = (int)($order->service_id ?? 0);
        if ($userId <= 0 || $serviceId <= 0) {
            return;
        }

        $user = User::query()->lockForUpdate()->find($userId);
        if (!$user) {
            throw new \RuntimeException('FILE_GROUP_PRICING_USER_MISSING');
        }

        $groupId = (int)($user->group_id ?? 0);
        if ($groupId <= 0 || !Schema::hasTable('service_group_prices')) {
            return;
        }

        $groupPrice = ServiceGroupPrice::query()
            ->where('service_type', 'file')
            ->where('service_id', $serviceId)
            ->where('group_id', $groupId)
            ->first();

        $price = $groupPrice?->finalPrice($groupPrice->auto_price ? $order->service : null);
        if ($price === null) {
            return;
        }

        $groupSell = $this->money(max(0, $price));
        $parentSell = $this->money($order->price ?? 0);
        $cost = $this->money($order->order_price ?? 0);
        $adjustment = bcsub($groupSell, $parentSell, 2);
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

        $order->price = $groupSell;
        $order->profit = bcsub($groupSell, $cost, 2);

        $request['charged_amount'] = (float)$groupSell;
        $request['group_id'] = $groupId;
        $request['billing_mode'] = 'file_group_price';
        $request['file_group_pricing_applied'] = true;
        $order->request = $request;
    }

    private function money(mixed $value): string
    {
        return is_numeric($value) ? number_format((float)$value, 2, '.', '') : '0.00';
    }
}
