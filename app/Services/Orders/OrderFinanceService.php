<?php

namespace App\Services\Orders;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrderFinanceService
{
    private const SCALE = 4;

    private function money(mixed $value): string
    {
        if (!is_numeric($value)) {
            return '0.0000';
        }

        return number_format((float)$value, self::SCALE, '.', '');
    }

    private function financialState(array $request): string
    {
        $state = strtolower(trim((string)($request['financial_state'] ?? '')));
        if (in_array($state, ['charged', 'refunded'], true)) {
            return $state;
        }

        if (!empty($request['refunded_at']) && empty($request['recharged_at'])) {
            return 'refunded';
        }

        return 'charged';
    }

    public function refundOrderIfNeeded(Model $order, string $reason): bool
    {
        $changed = DB::transaction(function () use ($order, $reason): bool {
            /** @var Model|null $lockedOrder */
            $lockedOrder = $order->newQuery()->lockForUpdate()->find($order->getKey());
            if (!$lockedOrder) {
                return false;
            }

            $request = (array)($lockedOrder->request ?? []);
            if ($this->financialState($request) === 'refunded') {
                return false;
            }

            $uid = (int)($lockedOrder->user_id ?? 0);
            $amount = $this->money($request['charged_amount'] ?? 0);
            if ($uid <= 0 || bccomp($amount, '0.0000', self::SCALE) !== 1) {
                return false;
            }

            $user = User::query()->lockForUpdate()->find($uid);
            if (!$user) {
                return false;
            }

            $balance = $this->money($user->balance ?? 0);
            $user->balance = bcadd($balance, $amount, self::SCALE);
            $user->save();

            $request['financial_state'] = 'refunded';
            $request['refunded_at'] = now()->toDateTimeString();
            $request['refunded_amount'] = (float)$amount;
            $request['refunded_reason'] = $reason;

            $lockedOrder->request = $request;
            $lockedOrder->save();

            return true;
        });

        if ($order->exists) {
            $order->refresh();
        }

        return $changed;
    }

    public function rechargeOrderIfNeeded(Model $order, string $reason, bool $allowNegative = false): bool
    {
        $changed = DB::transaction(function () use ($order, $reason, $allowNegative): bool {
            /** @var Model|null $lockedOrder */
            $lockedOrder = $order->newQuery()->lockForUpdate()->find($order->getKey());
            if (!$lockedOrder) {
                return false;
            }

            $request = (array)($lockedOrder->request ?? []);
            if ($this->financialState($request) !== 'refunded') {
                return false;
            }

            $uid = (int)($lockedOrder->user_id ?? 0);
            $amount = $this->money($request['charged_amount'] ?? 0);
            if ($uid <= 0 || bccomp($amount, '0.0000', self::SCALE) !== 1) {
                return false;
            }

            $user = User::query()->lockForUpdate()->find($uid);
            if (!$user) {
                return false;
            }

            $balance = $this->money($user->balance ?? 0);
            if (!$allowNegative && bccomp($balance, $amount, self::SCALE) === -1) {
                throw new \RuntimeException('INSUFFICIENT_BALANCE_RECHARGE');
            }

            $user->balance = bcsub($balance, $amount, self::SCALE);
            $user->save();

            $request['financial_state'] = 'charged';
            $request['recharged_at'] = now()->toDateTimeString();
            $request['recharged_amount'] = (float)$amount;
            $request['recharged_reason'] = $reason;
            if ($allowNegative && bccomp($balance, $amount, self::SCALE) === -1) {
                $request['recharged_with_negative_balance'] = true;
            }

            $lockedOrder->request = $request;
            $lockedOrder->save();

            return true;
        });

        if ($order->exists) {
            $order->refresh();
        }

        return $changed;
    }
}
