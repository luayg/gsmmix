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
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();
    }

    protected $commands = [
        \App\Console\Commands\ProvidersSyncCommand::class,
        // ❌ لا نُسجل SyncDhruServices نهائيًا (سيتم حذف الملف)
    ];
}
