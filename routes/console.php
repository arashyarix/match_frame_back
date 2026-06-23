<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// MatchFrame worker: process queued analyses and reveal due reports.
// In production add a single cron entry: * * * * * php artisan schedule:run
Schedule::command('analyses:process')->everyMinute()->withoutOverlapping();
