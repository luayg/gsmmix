<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserFinanceController extends Controller
{
    /** شاشة الملخص الرئيسية */
    public function modal(User $user)
    {
        return view('admin.users.modals.finances', compact('user'));
    }

    /** مزامنة رصيد المستخدم في جدول users (الرصيد المتاح) */
    private function syncUserBalance(User $user, FinanceAccount $acc): void
    {
        // available = paid - locked + overdraft
        $available = bcadd(
            bcsub($acc->paid_credits, $acc->locked_amount, 2),
            $acc->overdraft_limit,
            2
        );
        if (bccomp($available, '0.00', 2) === -1) {
            $available = '0.00';
        }

        // خزّن نفس القيمة في users.balance لظهورها في صفحة المستخدمين
        $user->forceFill(['balance' => $available])->save();
    }

    /** ملخص الأرقام (يرجع JSON) */
    public function summary(User $user)
    {
        $acc = FinanceAccount::firstOrCreate(
            ['user_id' => $user->id],
            ['locked_amount' => 0, 'total_receipts' => 0, 'paid_credits' => 0, 'overdraft_limit' => 0]
        );

        // Unpaid = total - paid (بدون سالب)
        $unpaid = bcsub($acc->total_receipts, $acc->paid_credits, 2);
        if (bccomp($unpaid, '0.00', 2) === -1) {
            $unpaid = '0.00';
        }

        // الرصيد المتاح = paid - locked + overdraft
        $available = bcadd(
            bcsub($acc->paid_credits, $acc->locked_amount, 2),
            $acc->overdraft_limit,
            2
        );
        if (bccomp($available, '0.00', 2) === -1) {
            $available = '0.00';
        }

        // مزامنة users.balance
        $this->syncUserBalance($user, $acc);

        return response()->json([
            // Balance الآن = الرصيد المتاح
            'balance'        => number_format($available, 2),
            'available'      => number_format($available, 2),
            'locked'         => number_format($acc->locked_amount, 2),
            'total_receipts' => number_format($acc->total_receipts, 2),
            'paid'           => number_format($acc->paid_credits, 2),
            'unpaid'         => number_format($unpaid, 2),
            'duty'           => number_format($unpaid, 2),
            'overdraft'      => number_format($acc->overdraft_limit, 2),
        ]);
    }

    /** كشف الحساب (جدول) */
    public function statement(User $user, Request $request)
    {
        $rows = FinanceTransaction::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('admin.users.finances.forms.statement', compact('user', 'rows'));
    }

    /* ================== GET Forms ================== */

    public function formOverdraft(User $user)
    {
        $acc = FinanceAccount::firstOrCreate(
            ['user_id' => $user->id],
            ['locked_amount' => 0, 'total_receipts' => 0, 'paid_credits' => 0, 'overdraft_limit' => 0]
        );

        return view('admin.users.finances.forms.overdraft', [
            'user' => $user,
            'acc'  => $acc,
        ]);
    }

    public function formAddRemove(User $user)
    {
        $acc = FinanceAccount::firstOrCreate(
            ['user_id' => $user->id],
            ['locked_amount' => 0, 'total_receipts' => 0, 'paid_credits' => 0, 'overdraft_limit' => 0]
        );

        $unpaid = max(0, (float)$acc->total_receipts - (float)$acc->paid_credits);

        return view('admin.users.finances.forms.add_remove', [
            'user'   => $user,
            'acc'    => $acc,
            'unpaid' => $unpaid,
        ]);
    }

    public function formAddPayment(User $user)
    {
        $acc = FinanceAccount::firstOrCreate(
            ['user_id' => $user->id],
            ['locked_amount' => 0, 'total_receipts' => 0, 'paid_credits' => 0, 'overdraft_limit' => 0]
        );

        $duty = max(0, (float)$acc->total_receipts - (float)$acc->paid_credits);

        return view('admin.users.finances.forms.add_payment', [
            'user' => $user,
            'acc'  => $acc,
            'duty' => $duty,
        ]);
    }

    public function formGateways(User $user)
    {
        return view('admin.users.finances.forms.gateways', compact('user'));
    }

    /* ================== POST Actions ================== */

    public function setOverdraft(Request $r, User $user)
    {
        $data = $r->validate([
            'overdraft' => 'required|numeric|min:0',
        ]);

        $acc = FinanceAccount::firstOrCreate(
            ['user_id' => $user->id],
            ['locked_amount' => 0, 'total_receipts' => 0, 'paid_credits' => 0, 'overdraft_limit' => 0]
        );

        $acc->overdraft_limit = $data['overdraft'];
        $acc->save();

        // مزامنة users.balance
        $this->syncUserBalance($user, $acc);

        return response()->json(['ok' => true, 'msg' => 'Overdraft updated']);
    }

    public function addRemoveCredits(Request $r, User $user)
{
    $data = $r->validate([
        'action' => 'required|in:add,remove',
        'amount' => 'required|numeric|min:0.01',
        'paid'   => 'nullable|boolean',
        'note'   => 'nullable|string|max:1000',
    ]);

    $acc = FinanceAccount::firstOrCreate(
        ['user_id' => $user->id],
        ['locked_amount' => 0, 'total_receipts' => 0, 'paid_credits' => 0, 'overdraft_limit' => 0]
    );

    DB::transaction(function () use ($data, $user, $acc) {
        $amount = number_format($data['amount'], 2, '.', '');

        if ($data['action'] === 'add') {
            // إضافة رصيد
            $acc->total_receipts = bcadd($acc->total_receipts, $amount, 2);

            $paidFlag = !empty($data['paid']) ? 1 : 0;
            if ($paidFlag) {
                $acc->paid_credits = bcadd($acc->paid_credits, $amount, 2);
            }

            $balance = bcsub($acc->paid_credits, $acc->locked_amount, 2);

            FinanceTransaction::create([
                'user_id'       => $user->id,
                'kind'          => 'credit_add',
                'direction'     => 'income',
                'paid'          => $paidFlag,
                'amount'        => $amount,
                'reference'     => 'manual_add',
                'note'          => $data['note'] ?? null,
                'balance_after' => $balance,
            ]);

        } else {
            // حذف رصيد: احذف بالكامل من Total receipts،
            // ثم قصّ paid_credits إن تجاوز total_receipts الجديد
            $newTotal = bcsub($acc->total_receipts, $amount, 2);
            if (bccomp($newTotal, '0.00', 2) === -1) {
                $newTotal = '0.00';
            }

            // إن أصبح paid_credits > newTotal، اخفضه ليطابق newTotal
            $paidAfter = $acc->paid_credits;
            if (bccomp($paidAfter, $newTotal, 2) === 1) {
                $paidAfter = $newTotal;
            }

            $acc->total_receipts = $newTotal;
            $acc->paid_credits   = $paidAfter;

            $balance = bcsub($paidAfter, $acc->locked_amount, 2);

            FinanceTransaction::create([
                'user_id'       => $user->id,
                'kind'          => 'credit_remove',
                'direction'     => 'expense',
                'paid'          => 0,
                'amount'        => $amount,
                'reference'     => 'manual_remove',
                'note'          => $data['note'] ?? null, // بدون "refund from paid"
                'balance_after' => $balance,
            ]);
        }

        $acc->save();
    });

    // مزامنة users.balance (الرصيد المتاح)
    $this->syncUserBalance($user, $acc);

    return response()->json(['ok' => true, 'msg' => 'Credits updated']);
}


    public function addPayment(Request $r, User $user)
    {
        $data = $r->validate([
            'amount' => 'required|numeric|min:0.01',
            'note'   => 'nullable|string|max:1000',
        ]);

        $acc = FinanceAccount::firstOrCreate(
            ['user_id' => $user->id],
            ['locked_amount' => 0, 'total_receipts' => 0, 'paid_credits' => 0, 'overdraft_limit' => 0]
        );

        DB::transaction(function () use ($data, $user, $acc) {
            $amount = number_format($data['amount'], 2, '.', '');

            $unpaid = bcsub($acc->total_receipts, $acc->paid_credits, 2);
            if (bccomp($unpaid, '0.00', 2) === -1) $unpaid = '0.00';

            if (bccomp($amount, $unpaid, 2) === 1) {
                abort(422, 'Amount exceeds unpaid credits.');
            }

            $acc->paid_credits = bcadd($acc->paid_credits, $amount, 2);

            $balance = bcsub($acc->paid_credits, $acc->locked_amount, 2);

            FinanceTransaction::create([
                'user_id'       => $user->id,
                'kind'          => 'payment',
                'direction'     => 'income',
                'paid'          => 1,
                'amount'        => $amount,
                'reference'     => 'manual_payment',
                'note'          => $data['note'] ?? null,
                'balance_after' => $balance,
            ]);

            $acc->save();
        });

        // مزامنة users.balance
        $this->syncUserBalance($user, $acc);

        return response()->json(['ok' => true, 'msg' => 'Payment added']);
    }
}
