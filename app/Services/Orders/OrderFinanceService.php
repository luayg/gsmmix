<?php

namespace App\Services\Orders;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrderFinanceService
{
    public function refundOrderIfNeeded(Model $order, string $reason): void
    {
        $req = (array)($order->request ?? []);
        if (!empty($req['refunded_at'])) {
            return;
        }

        $uid = (int)($order->user_id ?? 0);
        if ($uid <= 0) {
            return;
        }

        $amount = (float)($req['charged_amount'] ?? 0);
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $uid, $amount, $reason, $req) {
            $u = User::query()->lockForUpdate()->find($uid);
            if (!$u) {
                return;
            }

            $u->balance = (float)($u->balance ?? 0) + $amount;
            $u->save();

            $req['refunded_at'] = now()->toDateTimeString();
            $req['refunded_amount'] = $amount;
            $req['refunded_reason'] = $reason;

            $order->request = $req;
            $order->save();
        });
    }

    public function rechargeOrderIfNeeded(Model $order, string $reason): void
    {
        $req = (array)($order->request ?? []);

        if (empty($req['refunded_at'])) {
            return;
        }

        if (!empty($req['recharged_at'])) {
            return;
        }

        $uid = (int)($order->user_id ?? 0);
        if ($uid <= 0) {
            return;
        }

        $amount = (float)($req['charged_amount'] ?? 0);
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $uid, $amount, $reason, $req) {
            $u = User::query()->lockForUpdate()->find($uid);
            if (!$u) {
                return;
            }

            $bal = (float)($u->balance ?? 0);
            if ($bal < $amount) {
                throw new \RuntimeException('INSUFFICIENT_BALANCE_RECHARGE');
            }

            $u->balance = $bal - $amount;
            $u->save();

            $req['recharged_at'] = now()->toDateTimeString();
            $req['recharged_amount'] = $amount;
            $req['recharged_reason'] = $reason;

            $order->request = $req;
            $order->save();
        });
    }
}