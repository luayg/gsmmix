<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserDeletionGuard
{
    /**
     * Return the historical/financial data that makes hard deletion unsafe.
     * Zero-value finance account shells are intentionally not blockers.
     *
     * @return array{
     *   order_counts:array<string,int>,
     *   orders_total:int,
     *   finance_transactions:int,
     *   balance:float,
     *   finance_account_nonzero:bool,
     *   finance_account_values:array<string,float>,
     *   blocked:bool
     * }
     */
    public function inspect(User $user): array
    {
        $userId = (int) $user->id;
        $orderCounts = [];

        foreach (['imei_orders', 'server_orders', 'file_orders', 'smm_orders', 'product_orders'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            $orderCounts[$table] = DB::table($table)->where('user_id', $userId)->count();
        }

        $ordersTotal = array_sum($orderCounts);

        $financeTransactions = Schema::hasTable('finance_transactions') && Schema::hasColumn('finance_transactions', 'user_id')
            ? DB::table('finance_transactions')->where('user_id', $userId)->count()
            : 0;

        $balance = (float) ($user->balance ?? 0);
        $financeAccountValues = [];
        $financeAccountNonzero = false;

        if (Schema::hasTable('finance_accounts') && Schema::hasColumn('finance_accounts', 'user_id')) {
            $columns = array_values(array_intersect(
                ['locked_amount', 'total_receipts', 'paid_credits', 'overdraft_limit'],
                Schema::getColumnListing('finance_accounts')
            ));

            if ($columns !== []) {
                $account = DB::table('finance_accounts')
                    ->where('user_id', $userId)
                    ->first($columns);

                if ($account) {
                    foreach ($columns as $column) {
                        $value = (float) ($account->{$column} ?? 0);
                        $financeAccountValues[$column] = $value;
                        if (abs($value) > 0.000001) {
                            $financeAccountNonzero = true;
                        }
                    }
                }
            }
        }

        $blocked = $ordersTotal > 0
            || $financeTransactions > 0
            || abs($balance) > 0.000001
            || $financeAccountNonzero;

        return [
            'order_counts' => $orderCounts,
            'orders_total' => $ordersTotal,
            'finance_transactions' => $financeTransactions,
            'balance' => $balance,
            'finance_account_nonzero' => $financeAccountNonzero,
            'finance_account_values' => $financeAccountValues,
            'blocked' => $blocked,
        ];
    }

    public function message(array $inspection): string
    {
        $parts = [];

        if (($inspection['orders_total'] ?? 0) > 0) {
            $parts[] = (int) $inspection['orders_total'] . ' historical order(s)';
        }

        if (($inspection['finance_transactions'] ?? 0) > 0) {
            $parts[] = (int) $inspection['finance_transactions'] . ' finance transaction(s)';
        }

        if (abs((float) ($inspection['balance'] ?? 0)) > 0.000001) {
            $parts[] = 'non-zero balance';
        }

        if (!empty($inspection['finance_account_nonzero'])) {
            $parts[] = 'non-zero finance account totals';
        }

        return 'User cannot be deleted because ' . implode(', ', $parts) . ' must be preserved.';
    }
}
