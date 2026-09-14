<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditUserReferences extends Command
{
    protected $signature = 'users:reference-audit {--details=50 : Maximum risky rows to display}';

    protected $description = 'Read-only audit of order, finance, role, and permission references to users.';

    public function handle(): int
    {
        $state = [
            'users_scanned' => 0,
            'order_tables_scanned' => 0,
            'orders_scanned' => 0,
            'order_missing_user' => 0,
            'finance_accounts_scanned' => 0,
            'finance_account_missing_user' => 0,
            'duplicate_finance_account_user' => 0,
            'finance_transactions_scanned' => 0,
            'finance_transaction_missing_user' => 0,
            'orphan_user_role_pivots' => 0,
            'orphan_user_permission_pivots' => 0,
        ];

        $details = [];
        $limit = max(0, min(200, (int) $this->option('details')));

        if (!Schema::hasTable('users')) {
            $this->error('users table is missing.');
            return self::FAILURE;
        }

        $userIds = DB::table('users')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();
        $state['users_scanned'] = count($userIds);

        foreach (['imei_orders', 'server_orders', 'file_orders', 'smm_orders', 'product_orders'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            $state['order_tables_scanned']++;
            $columns = Schema::getColumnListing($table);
            $select = array_values(array_intersect(['id', 'user_id', 'status'], $columns));

            foreach (DB::table($table)->orderBy('id')->get($select) as $row) {
                $state['orders_scanned']++;
                $userId = (int) ($row->user_id ?? 0);

                if ($userId <= 0 || !isset($userIds[$userId])) {
                    $state['order_missing_user']++;
                    $this->addDetail($details, $limit, [
                        'entity' => $table,
                        'id' => (int) $row->id,
                        'user_id' => $userId > 0 ? $userId : '—',
                        'risk' => 'order_missing_user',
                    ]);
                }
            }
        }

        if (Schema::hasTable('finance_accounts') && Schema::hasColumn('finance_accounts', 'user_id')) {
            $seen = [];
            $columns = Schema::getColumnListing('finance_accounts');
            $select = array_values(array_intersect(['id', 'user_id'], $columns));

            foreach (DB::table('finance_accounts')->orderBy('id')->get($select) as $row) {
                $state['finance_accounts_scanned']++;
                $userId = (int) ($row->user_id ?? 0);
                $risks = [];

                if ($userId <= 0 || !isset($userIds[$userId])) {
                    $state['finance_account_missing_user']++;
                    $risks[] = 'finance_account_missing_user';
                }

                if ($userId > 0) {
                    if (isset($seen[$userId])) {
                        $state['duplicate_finance_account_user']++;
                        $risks[] = 'duplicate_finance_account_user';
                    } else {
                        $seen[$userId] = (int) $row->id;
                    }
                }

                if ($risks !== []) {
                    $this->addDetail($details, $limit, [
                        'entity' => 'finance_accounts',
                        'id' => (int) $row->id,
                        'user_id' => $userId > 0 ? $userId : '—',
                        'risk' => implode(',', $risks),
                    ]);
                }
            }
        }

        if (Schema::hasTable('finance_transactions') && Schema::hasColumn('finance_transactions', 'user_id')) {
            $columns = Schema::getColumnListing('finance_transactions');
            $select = array_values(array_intersect(['id', 'user_id', 'kind'], $columns));

            foreach (DB::table('finance_transactions')->orderBy('id')->get($select) as $row) {
                $state['finance_transactions_scanned']++;
                $userId = (int) ($row->user_id ?? 0);

                if ($userId <= 0 || !isset($userIds[$userId])) {
                    $state['finance_transaction_missing_user']++;
                    $this->addDetail($details, $limit, [
                        'entity' => 'finance_transactions',
                        'id' => (int) $row->id,
                        'user_id' => $userId > 0 ? $userId : '—',
                        'risk' => 'finance_transaction_missing_user',
                    ]);
                }
            }
        }

        $userModelType = User::class;
        $state['orphan_user_role_pivots'] = $this->countOrphanModelPivots(
            'model_has_roles',
            $userModelType,
            $userIds,
            'orphan_user_role_pivots',
            $details,
            $limit
        );
        $state['orphan_user_permission_pivots'] = $this->countOrphanModelPivots(
            'model_has_permissions',
            $userModelType,
            $userIds,
            'orphan_user_permission_pivots',
            $details,
            $limit
        );

        $this->info('Read-only user reference integrity audit');
        $this->table(
            ['State', 'Count'],
            collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        if ($details !== []) {
            $this->newLine();
            $this->warn('User reference risks (read-only):');
            $this->table(
                ['Entity', 'ID', 'User ID', 'Risk'],
                array_map(fn (array $row) => [
                    $row['entity'], $row['id'], $row['user_id'], $row['risk'],
                ], $details)
            );
        }

        $problemKeys = [
            'order_missing_user',
            'finance_account_missing_user',
            'duplicate_finance_account_user',
            'finance_transaction_missing_user',
            'orphan_user_role_pivots',
            'orphan_user_permission_pivots',
        ];

        return collect($problemKeys)->contains(fn ($key) => $state[$key] > 0)
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function countOrphanModelPivots(
        string $table,
        string $userModelType,
        array $userIds,
        string $risk,
        array &$details,
        int $limit
    ): int {
        if (!Schema::hasTable($table)
            || !Schema::hasColumn($table, 'model_type')
            || !Schema::hasColumn($table, 'model_id')) {
            return 0;
        }

        $count = 0;
        $idColumn = Schema::hasColumn($table, 'id') ? 'id' : null;
        $select = array_filter([$idColumn, 'model_id']);

        $rows = DB::table($table)
            ->where('model_type', $userModelType)
            ->get(array_values($select));

        foreach ($rows as $index => $row) {
            $userId = (int) ($row->model_id ?? 0);
            if ($userId > 0 && isset($userIds[$userId])) {
                continue;
            }

            $count++;
            $this->addDetail($details, $limit, [
                'entity' => $table,
                'id' => $idColumn ? (int) $row->{$idColumn} : ($index + 1),
                'user_id' => $userId > 0 ? $userId : '—',
                'risk' => $risk,
            ]);
        }

        return $count;
    }

    private function addDetail(array &$details, int $limit, array $row): void
    {
        if (count($details) < $limit) {
            $details[] = $row;
        }
    }
}
