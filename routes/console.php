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
*/

Schedule::command('providers:sync')
    ->everyMinute()
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
