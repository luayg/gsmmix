<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // كل ساعة (عدّلها كما تريد: everyFifteenMinutes, dailyAt('02:00')...)
        $schedule->command('dhru:sync --provider=all')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer(); // لو عندك أكثر من عامل/سيرفر
    }
    protected $commands = [
    \App\Console\Commands\ProvidersSyncCommand::class,
];
}
