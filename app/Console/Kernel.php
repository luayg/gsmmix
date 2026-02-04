<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // ✅ نظام واحد فقط للمزامنة (ProviderManager + Adapters)
        $schedule->command('providers:sync')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();
        // إعادة إرسال الطلبات التي فشل إرسالها (كل دقيقة)
    $schedule->command('orders:retry-imei --limit=20')->everyMinute();

    // مزامنة نتائج الطلبات (مثلاً كل 5 دقائق)
    $schedule->command('orders:sync-imei --limit=50')->everyMinute();

    $schedule->command('orders:dispatch-pending-imei --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

    }

    protected $commands = [
        \App\Console\Commands\ProvidersSyncCommand::class,
        // ❌ لا نُسجل SyncDhruServices نهائيًا (سيتم حذف الملف)
    ];
}
