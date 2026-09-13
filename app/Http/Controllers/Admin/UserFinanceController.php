<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserFinanceController extends Controller
{
    private function money(mixed $value): string
    {
        return is_numeric($value) ? number_format((float)$value, 2, '.', '') : '0.00';
    }

    private function accountDefaults(int $userId): array
    {
        return [
            'user_id' => $userId,
            'locked_amount' => 0,
            'total_receipts' => 0,
            'paid_credits' => 0,
            'overdraft_limit' => 0,
        ];
    }

    private function accountForDisplay(User $user): FinanceAccount
    {
        return FinanceAccount::query()->where('user_id', $user->id)->first()
            ?? new FinanceAccount($this->accountDefaults((int)$user->id));
    }

    /** Call only after the matching users row has been locked. */
    private function accountForUpdate(int $userId): FinanceAccount
    {
        FinanceAccount::firstOrCreate(
            ['user_id' => $userId],
            $this->accountDefaults($userId)
        );

        return FinanceAccount::query()
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function availableBalance(User $user): string
    {
        return $this->money($user->balance ?? 0);
    }

    public function modal(User $user)
    {
        return view('admin.users.modals.finances', compact('user'));
    }

    /** Summary is read-only: order debits/refunds make users.balance canonical. */
    public function summary(User $user)
    {
        $user->refresh();
        $acc = $this->accountForDisplay($user);
        $available = $this->availableBalance($user);

        $unpaid = bcsub($this->money($acc->total_receipts), $this->money($acc->paid_credits), 2);
        if (bccomp($unpaid, '0.00', 2) === -1) {
            $unpaid = '0.00';
        }

        return response()->json([
            'balance'        => number_format((float)$available, 2),
            'available'      => number_format((float)$available, 2),
            'locked'         => number_format((float)$acc->locked_amount, 2),
            'total_receipts' => number_format((float)$acc->total_receipts, 2),
            'paid'           => number_format((float)$acc->paid_credits, 2),
            'unpaid'         => number_format((float)$unpaid, 2),
            'duty'           => number_format((float)$unpaid, 2),
            'overdraft'      => number_format((float)$acc->overdraft_limit, 2),
        ]);
    }

    public function statement(User $user, Request $request)
    {
        $rows = FinanceTransaction::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('admin.users.finances.forms.statement', compact('user', 'rows'));
    }

    public function formOverdraft(User $user)
    {
        $acc = FinanceAccount::firstOrCreate(
            ['user_id' => $user->id],
            $this->accountDefaults((int)$user->id)
        );

        return view('admin.users.finances.forms.overdraft', compact('user', 'acc'));
    }

    public function formAddRemove(User $user)
    {
        $acc = FinanceAccount::firstOrCreate(
            ['user_id' => $user->id],
            $this->accountDefaults((int)$user->id)
        );
        $unpaid = max(0, (float)$acc->total_receipts - (float)$acc->paid_credits);

        return view('admin.users.finances.forms.add_remove', compact('user', 'acc', 'unpaid'));
    }

    public function formAddPayment(User $user)
    {
        $acc = FinanceAccount::firstOrCreate(
            ['user_id' => $user->id],
            $this->accountDefaults((int)$user->id)
        );
        $duty = max(0, (float)$acc->total_receipts - (float)$acc->paid_credits);

        return view('admin.users.finances.forms.add_payment', compact('user', 'acc', 'duty'));
    }

    public function formGateways(User $user)
    {
        return view('admin.users.finances.forms.gateways', compact('user'));
    }

    public function setOverdraft(Request $request, User $user)
    {
        $data = $request->validate(['overdraft' => 'required|numeric|min:0']);

        DB::transaction(function () use ($data, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $acc = $this->accountForUpdate((int)$lockedUser->id);

            $old = $this->money($acc->overdraft_limit);
            $new = $this->money($data['overdraft']);
            $delta = bcsub($new, $old, 2);
            $balance = $this->availableBalance($lockedUser);
            $newBalance = bcadd($balance, $delta, 2);

            if (bccomp($newBalance, '0.00', 2) === -1) {
                abort(422, 'Overdraft cannot be reduced below the amount already used.');
            }

            $acc->overdraft_limit = $new;
            $acc->save();
            $lockedUser->balance = $newBalance;
            $lockedUser->save();

            if (bccomp($delta, '0.00', 2) !== 0) {
                FinanceTransaction::create([
                    'user_id' => $lockedUser->id,
                    'kind' => 'overdraft_set',
                    'direction' => bccomp($delta, '0.00', 2) === 1 ? 'income' : 'expense',
                    'paid' => 0,
                    'amount' => ltrim($delta, '-') ?: '0.00',
                    'reference' => 'overdraft_set',
                    'note' => 'Overdraft limit changed',
                    'balance_after' => $newBalance,
                ]);
            }
        });

        return response()->json(['ok' => true, 'msg' => 'Overdraft updated']);
    }

    public function addRemoveCredits(Request $request, User $user)
    {
        $data = $request->validate([
            'action' => 'required|in:add,remove',
            'amount' => 'required|numeric|min:0.01',
            'paid'   => 'nullable|boolean',
            'note'   => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($data, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $acc = $this->accountForUpdate((int)$lockedUser->id);
            $amount = $this->money($data['amount']);
            $balance = $this->availableBalance($lockedUser);

            if ($data['action'] === 'add') {
                $paidFlag = !empty($data['paid']) ? 1 : 0;
                $acc->total_receipts = bcadd($this->money($acc->total_receipts), $amount, 2);
                if ($paidFlag) {
                    $acc->paid_credits = bcadd($this->money($acc->paid_credits), $amount, 2);
                    $balance = bcadd($balance, $amount, 2);
                    $lockedUser->balance = $balance;
                    $lockedUser->save();
                }

                FinanceTransaction::create([
                    'user_id' => $lockedUser->id,
                    'kind' => 'credit_add',
                    'direction' => 'income',
                    'paid' => $paidFlag,
                    'amount' => $amount,
                    'reference' => 'manual_add',
                    'note' => $data['note'] ?? null,
                    'balance_after' => $balance,
                ]);
            } else {
                $oldTotal = $this->money($acc->total_receipts);
                $oldPaid = $this->money($acc->paid_credits);
                $newTotal = bcsub($oldTotal, $amount, 2);
                if (bccomp($newTotal, '0.00', 2) === -1) {
                    $newTotal = '0.00';
                }

                $actualRemoved = bcsub($oldTotal, $newTotal, 2);
                $newPaid = bccomp($oldPaid, $newTotal, 2) === 1 ? $newTotal : $oldPaid;
                $paidReduction = bcsub($oldPaid, $newPaid, 2);

                if (bccomp($balance, $paidReduction, 2) === -1) {
                    abort(422, 'Cannot remove paid credits that have already been spent.');
                }

                $acc->total_receipts = $newTotal;
                $acc->paid_credits = $newPaid;
                if (bccomp($paidReduction, '0.00', 2) === 1) {
                    $balance = bcsub($balance, $paidReduction, 2);
                    $lockedUser->balance = $balance;
                    $lockedUser->save();
                }

                FinanceTransaction::create([
                    'user_id' => $lockedUser->id,
                    'kind' => 'credit_remove',
                    'direction' => 'expense',
                    'paid' => 0,
                    'amount' => $actualRemoved,
                    'reference' => 'manual_remove',
                    'note' => $data['note'] ?? null,
                    'balance_after' => $balance,
                ]);
            }

            $acc->save();
        });

        return response()->json(['ok' => true, 'msg' => 'Credits updated']);
    }

    public function addPayment(Request $request, User $user)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'note'   => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($data, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $acc = $this->accountForUpdate((int)$lockedUser->id);
            $amount = $this->money($data['amount']);

            $unpaid = bcsub($this->money($acc->total_receipts), $this->money($acc->paid_credits), 2);
            if (bccomp($unpaid, '0.00', 2) === -1) {
                $unpaid = '0.00';
            }
            if (bccomp($amount, $unpaid, 2) === 1) {
                abort(422, 'Amount exceeds unpaid credits.');
            }

            $acc->paid_credits = bcadd($this->money($acc->paid_credits), $amount, 2);
            $acc->save();

            $balance = bcadd($this->availableBalance($lockedUser), $amount, 2);
            $lockedUser->balance = $balance;
            $lockedUser->save();

            FinanceTransaction::create([
                'user_id' => $lockedUser->id,
                'kind' => 'payment',
                'direction' => 'income',
                'paid' => 1,
                'amount' => $amount,
                'reference' => 'manual_payment',
                'note' => $data['note'] ?? null,
                'balance_after' => $balance,
            ]);
        });

        return response()->json(['ok' => true, 'msg' => 'Payment added']);
    }
}
