<?php

namespace App\Console\Commands;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditSchedulerReadiness extends Command
{
    protected $signature = 'orders:scheduler-audit {--json : Output JSON instead of a table}';

    protected $description = 'Read-only audit of scheduler registration, locking, and orders that would be dispatched.';

    public function handle(Schedule $schedule): int
    {
        $required = [
            'providers:sync' => false,
            'orders:dispatch-pending-imei --limit=50' => true,
            'orders:sync-imei --limit=50' => false,
            'orders:dispatch-pending-server --limit=50' => true,
            'orders:sync-server --limit=50' => false,
            'orders:dispatch-pending-file --limit=50' => true,
            'orders:sync-file --limit=50' => false,
            'orders:dispatch-pending-smm --limit=50' => true,
            'orders:sync-smm --limit=50' => false,
        ];

        $events = collect($schedule->events());
        $missing = 0;
        $duplicate = 0;
        $withoutOverlapMissing = 0;
        $oneServerMissing = 0;
        $backgroundDispatchMissing = 0;

        foreach ($required as $needle => $needsBackground) {
            $matches = $events->filter(function ($event) use ($needle): bool {
                return str_contains((string)($event->command ?? ''), $needle);
            });

            if ($matches->isEmpty()) {
                $missing++;
                continue;
            }

            if ($matches->count() > 1) {
                $duplicate += $matches->count() - 1;
            }

            foreach ($matches as $event) {
                if (!(bool)($event->withoutOverlapping ?? false)) {
                    $withoutOverlapMissing++;
                }
                if (!(bool)($event->onOneServer ?? false)) {
                    $oneServerMissing++;
                }
                if ($needsBackground && !(bool)($event->runInBackground ?? false)) {
                    $backgroundDispatchMissing++;
                }
            }
        }

        $retryScheduled = $events->filter(function ($event): bool {
            return str_contains((string)($event->command ?? ''), 'orders:retry-imei');
        })->count();

        $cacheStore = (string)config('cache.default', '');
        $lockProvider = false;
        try {
            $lockProvider = Cache::store()->getStore() instanceof LockProvider;
        } catch (\Throwable) {
            $lockProvider = false;
        }

        $cacheTableReady = null;
        $cacheLocksTableReady = null;
        if ($cacheStore === 'database') {
            $cacheTableReady = Schema::hasTable((string)config('cache.stores.database.table', 'cache'));
            // Laravel's database lock store convention is cache_locks when no override is supplied.
            $lockTable = (string)(config('cache.stores.database.lock_table') ?: 'cache_locks');
            $cacheLocksTableReady = Schema::hasTable($lockTable);
        }

        $orderTables = ['imei_orders', 'server_orders', 'file_orders', 'smm_orders'];
        $tablesScanned = 0;
        $missingOrderTables = 0;
        $dispatchableNow = 0;
        $dispatchHold = 0;
        $syncableNow = 0;
        $inprogressWithoutRemoteId = 0;

        foreach ($orderTables as $table) {
            if (!Schema::hasTable($table)) {
                $missingOrderTables++;
                continue;
            }

            $tablesScanned++;

            $waitingRows = DB::table($table)
                ->select(['id', 'request'])
                ->where('api_order', 1)
                ->where('status', 'waiting')
                ->where(function ($query): void {
                    $query->whereNull('processing')->orWhere('processing', 0);
                })
                ->where(function ($query): void {
                    $query->whereNull('remote_id')->orWhere('remote_id', '');
                })
                ->get();

            foreach ($waitingRows as $row) {
                $request = $this->decodeRequest($row->request ?? null);
                $held = !empty($request['dispatch_hold']) || !empty(data_get($request, 'request.dispatch_hold'));
                if ($held) {
                    $dispatchHold++;
                } else {
                    $dispatchableNow++;
                }
            }

            $syncableNow += DB::table($table)
                ->where('api_order', 1)
                ->where('status', 'inprogress')
                ->whereNotNull('remote_id')
                ->where('remote_id', '<>', '')
                ->count();

            $inprogressWithoutRemoteId += DB::table($table)
                ->where('api_order', 1)
                ->where('status', 'inprogress')
                ->where(function ($query): void {
                    $query->whereNull('remote_id')->orWhere('remote_id', '');
                })
                ->count();
        }

        $state = [
            'scheduled_events_total' => $events->count(),
            'scheduled_pipeline_missing' => $missing,
            'scheduled_pipeline_duplicates' => $duplicate,
            'scheduled_retry_imei' => $retryScheduled,
            'without_overlap_missing' => $withoutOverlapMissing,
            'one_server_missing' => $oneServerMissing,
            'background_dispatch_missing' => $backgroundDispatchMissing,
            'cache_store' => $cacheStore,
            'cache_lock_provider' => $lockProvider ? 1 : 0,
            'cache_table_ready' => $cacheTableReady === null ? 'n/a' : ($cacheTableReady ? 1 : 0),
            'cache_locks_table_ready' => $cacheLocksTableReady === null ? 'n/a' : ($cacheLocksTableReady ? 1 : 0),
            'order_tables_scanned' => $tablesScanned,
            'missing_order_tables' => $missingOrderTables,
            'dispatchable_now' => $dispatchableNow,
            'dispatch_hold' => $dispatchHold,
            'syncable_now' => $syncableNow,
            'inprogress_without_remote_id' => $inprogressWithoutRemoteId,
        ];

        if ($this->option('json')) {
            $this->line((string)json_encode($state, JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Read-only scheduler readiness audit');
            $this->table(['State', 'Count / Value'], collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all());

            if ($dispatchableNow > 0) {
                $this->warn("Scheduler activation can submit {$dispatchableNow} waiting order(s) to providers.");
            }
        }

        $databaseLockFailure = $cacheStore === 'database' && (!$cacheTableReady || !$cacheLocksTableReady);
        $hasProblem = $missing > 0
            || $duplicate > 0
            || $retryScheduled > 0
            || $withoutOverlapMissing > 0
            || $oneServerMissing > 0
            || $backgroundDispatchMissing > 0
            || !$lockProvider
            || $databaseLockFailure
            || $missingOrderTables > 0
            || $inprogressWithoutRemoteId > 0;

        return $hasProblem ? self::FAILURE : self::SUCCESS;
    }

    private function decodeRequest(mixed $request): array
    {
        if (is_array($request)) {
            return $request;
        }

        if (is_string($request) && trim($request) !== '') {
            $decoded = json_decode($request, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
