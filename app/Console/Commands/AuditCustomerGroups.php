<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditCustomerGroups extends Command
{
    protected $signature = 'users:group-audit {--details=50 : Maximum risky rows to display}';

    protected $description = 'Read-only audit of customer group references used by users and service group pricing.';

    public function handle(): int
    {
        $state = [
            'groups_total' => Schema::hasTable('groups') ? DB::table('groups')->count() : 0,
            'users_scanned' => 0,
            'users_with_group' => 0,
            'users_missing_group' => 0,
            'group_prices_scanned' => 0,
            'group_prices_missing_group' => 0,
            'duplicate_group_names' => 0,
        ];

        $details = [];
        $limit = max(0, min(200, (int)$this->option('details')));

        $groupIds = Schema::hasTable('groups')
            ? DB::table('groups')->pluck('id')->map(fn ($id) => (int)$id)->flip()->all()
            : [];

        if (Schema::hasTable('groups') && Schema::hasColumn('groups', 'name')) {
            $duplicates = DB::table('groups')
                ->selectRaw('LOWER(TRIM(name)) as normalized_name, COUNT(*) as c')
                ->groupByRaw('LOWER(TRIM(name))')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            $state['duplicate_group_names'] = $duplicates->count();
            foreach ($duplicates as $dup) {
                if (count($details) >= $limit) break;
                $details[] = [
                    'entity' => 'group',
                    'id' => '—',
                    'group_id' => '—',
                    'risk' => 'duplicate_group_name:' . (string)$dup->normalized_name,
                ];
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'group_id')) {
            $users = DB::table('users')->orderBy('id')->get(['id', 'group_id']);
            $state['users_scanned'] = $users->count();

            foreach ($users as $user) {
                $groupId = (int)($user->group_id ?? 0);
                if ($groupId <= 0) continue;

                $state['users_with_group']++;
                if (!isset($groupIds[$groupId])) {
                    $state['users_missing_group']++;
                    if (count($details) < $limit) {
                        $details[] = [
                            'entity' => 'user',
                            'id' => (int)$user->id,
                            'group_id' => $groupId,
                            'risk' => 'missing_group',
                        ];
                    }
                }
            }
        }

        if (Schema::hasTable('service_group_prices') && Schema::hasColumn('service_group_prices', 'group_id')) {
            $rows = DB::table('service_group_prices')->orderBy('id')->get(['id', 'group_id']);
            $state['group_prices_scanned'] = $rows->count();

            foreach ($rows as $row) {
                $groupId = (int)($row->group_id ?? 0);
                if ($groupId <= 0 || isset($groupIds[$groupId])) continue;

                $state['group_prices_missing_group']++;
                if (count($details) < $limit) {
                    $details[] = [
                        'entity' => 'group_price',
                        'id' => (int)$row->id,
                        'group_id' => $groupId,
                        'risk' => 'missing_group',
                    ];
                }
            }
        }

        $this->info('Read-only customer group integrity audit');
        $this->table(
            ['State', 'Count'],
            collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        if ($details !== []) {
            $this->newLine();
            $this->warn('Customer group risks (read-only):');
            $this->table(
                ['Entity', 'ID', 'Group ID', 'Risk'],
                array_map(fn (array $r) => [$r['entity'], $r['id'], $r['group_id'], $r['risk']], $details)
            );
        }

        $problemKeys = ['users_missing_group', 'group_prices_missing_group', 'duplicate_group_names'];
        $hasProblems = collect($problemKeys)->contains(fn ($key) => $state[$key] > 0);

        return $hasProblems ? self::FAILURE : self::SUCCESS;
    }
}
