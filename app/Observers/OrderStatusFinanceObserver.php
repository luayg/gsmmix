<?php

namespace App\Observers;

use App\Services\Orders\OrderFinanceService;
use Illuminate\Database\Eloquent\Model;

/**
 * Last-line financial invariant for order status changes.
 *
 * Existing controller/dispatcher calls remain valid and idempotent. This observer
 * protects status changes made by provider-sync code or future code paths that do
 * not explicitly call OrderFinanceService.
 */
final class OrderStatusFinanceObserver
{
    public function updated(Model $order): void
    {
        if (!$order->wasChanged('status')) {
            return;
        }

        $status = strtolower(trim((string)($order->status ?? '')));
        $finance = app(OrderFinanceService::class);

        if (in_array($status, ['rejected', 'cancelled'], true)) {
            $finance->refundOrderIfNeeded($order, 'status_' . $status . '_auto');
            return;
        }

        // Only a confirmed success is allowed to force a previously refunded
        // charge back onto the account. Waiting/inprogress never force a debit.
        if ($status === 'success') {
            $finance->rechargeOrderIfNeeded($order, 'status_success_auto', true);
        }
    }
}
