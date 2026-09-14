<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        /**
         * Provider service/catalog sync.
         */
        $schedule->command('providers:sync')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        /**
         * Dispatch is the single automatic submission path for waiting API orders.
         * `orders:retry-imei` remains available as an operator command, but is not
         * scheduled separately; scheduling both could retry the same IMEI twice in
         * one minute after a transient provider failure.
         */
        $schedule->command('orders:dispatch-pending-imei --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('orders:sync-imei --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('orders:dispatch-pending-server --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('orders:sync-server --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('orders:dispatch-pending-file --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('orders:sync-file --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('orders:dispatch-pending-smm --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('orders:sync-smm --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Register commands explicitly.
     */
    protected $commands = [
        \App\Console\Commands\ProvidersSyncCommand::class,

        // Manual operator retry command; deliberately not scheduled.
        \App\Console\Commands\RetryImeiApiOrders::class,
        \App\Console\Commands\DispatchPendingImeiOrders::class,
        \App\Console\Commands\DispatchPendingServerOrders::class,
        \App\Console\Commands\DispatchPendingFileOrders::class,
        \App\Console\Commands\DispatchPendingSmmOrders::class,

        \App\Console\Commands\SyncImeiOrders::class,
        \App\Console\Commands\SyncServerOrders::class,
        \App\Console\Commands\SyncFileOrders::class,
        \App\Console\Commands\SyncSmmOrders::class,

        \App\Console\Commands\UserResetPasswordCommand::class,
    ];

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
