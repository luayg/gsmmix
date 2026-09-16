<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Provider / order scheduler
|--------------------------------------------------------------------------
|
| Laravel 12 loads console routes from this file through bootstrap/app.php.
| Keep one automatic submission path per order type. The legacy
| orders:retry-imei command remains available for manual operator use only.
|
| Provider balance refresh is intentionally lightweight and frequent, while
| full catalog/service synchronization is less frequent so provider APIs are
| not hit with expensive catalog requests every minute.
|
*/

Schedule::command('providers:sync --balance-only')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('providers:sync')
    ->hourlyAt(17)
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('orders:dispatch-pending-imei --limit=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('orders:sync-imei --limit=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('orders:dispatch-pending-server --limit=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('orders:sync-server --limit=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('orders:dispatch-pending-file --limit=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('orders:sync-file --limit=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('orders:dispatch-pending-smm --limit=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('orders:sync-smm --limit=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('payments:scan-usdt')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
