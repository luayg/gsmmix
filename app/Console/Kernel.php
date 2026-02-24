<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        /**
         * ✅ Provider Sync (Remote Tables)
         */
        $schedule->command('providers:sync')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        /**
         * ✅ Retry IMEI API orders that are waiting and not sent yet (no remote_id)
         */
        $schedule->command('orders:retry-imei --limit=20')
            ->everyMinute()
            ->withoutOverlapping();

        /**
         * ✅ Dispatch pending IMEI orders (also waiting + no remote_id)
         * ملاحظة: هذا قريب جدًا من retry-imei (ممكن تكتفي بواحد لاحقًا)
         */
        $schedule->command('orders:dispatch-pending-imei --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        /**
         * ✅ Sync results/status from providers
         */
        $schedule->command('orders:sync-imei --limit=50')
            ->everyMinute()
            ->withoutOverlapping();

            $schedule->command('orders:dispatch-pending-server --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('orders:sync-server --limit=50')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->command('orders:sync-file --limit=50')
            ->everyMinute()
            ->withoutOverlapping();
    }

    /**
     * ✅ Register commands explicitly (matches your Commands folder)
     */
    protected $commands = [
        \App\Console\Commands\ProvidersSyncCommand::class,

        \App\Console\Commands\RetryImeiApiOrders::class,
        \App\Console\Commands\DispatchPendingImeiOrders::class,
        \App\Console\Commands\DispatchPendingServerOrders::class,

        \App\Console\Commands\SyncImeiOrders::class,
        \App\Console\Commands\SyncServerOrders::class,
        \App\Console\Commands\SyncFileOrders::class,

        \App\Console\Commands\UserResetPasswordCommand::class,
    ];

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
