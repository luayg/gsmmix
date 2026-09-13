<?php

namespace App\Observers;

use App\Services\Orders\OrderFinanceService;
use Illuminate\Database\Eloquent\Model;

/**
 * Last-line status/financial invariants for orders.
 *
 * Existing controller/dispatcher calls remain valid and idempotent. This observer
 * protects status changes made by provider-sync code or future code paths that do
 * not explicitly call OrderFinanceService.
 */
final class OrderStatusFinanceObserver
{
    /**
     * Normalize status/processing before persistence so terminal orders can never
     * remain marked as processing, and a provider-owned order with a remote id is
     * never moved back to the unsent waiting state by a transient sync error.
     */
    public function saving(Model $order): void
    {
        $status = strtolower(trim((string)($order->status ?? '')));
        $remoteId = trim((string)($order->remote_id ?? ''));

        if (in_array($status, ['success', 'rejected', 'cancelled'], true)) {
            $order->processing = false;
            return;
        }

        if ($status === 'waiting' && $remoteId !== '') {
            $order->status = 'inprogress';
            $order->processing = true;
        }
    }

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
