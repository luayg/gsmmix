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
         * - يسحب خدمات المزودين ويحدّث remote_*_services
         */
        $schedule->command('providers:sync')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        /**
         * ✅ Retry sending (orders stuck because provider unreachable)
         * (هذه الأوامر موجودة عندك حسب ما وضعت)
         */
        $schedule->command('orders:retry-imei --limit=20')
            ->everyMinute()
            ->withoutOverlapping();

        /**
         * ✅ Sync results/status from providers
         */
        $schedule->command('orders:sync-imei --limit=50')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->command('orders:sync-server --limit=50')
            ->everyMinute()
            ->withoutOverlapping();

        // ✅ NEW: Sync File Orders
        $schedule->command('orders:sync-file --limit=50')
            ->everyMinute()
            ->withoutOverlapping();

        /**
         * ✅ Dispatch pending (if you have this command)
         * أنت مفعّل dispatch-pending-imei عندك
         */
        $schedule->command('orders:dispatch-pending-imei --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * ✅ Register commands explicitly (safe even with auto-discovery)
     */
    protected $commands = [
        \App\Console\Commands\ProvidersSyncCommand::class,

        \App\Console\Commands\SyncImeiOrders::class,
        \App\Console\Commands\SyncServerOrders::class,
        \App\Console\Commands\SyncFileOrders::class,

        // لو عندك هذا الأمر فعليًا
        \App\Console\Commands\RetryImeiOrders::class,

        // لو عندك هذا الأمر فعليًا
        \App\Console\Commands\DispatchPendingImeiOrders::class,
    ];

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
