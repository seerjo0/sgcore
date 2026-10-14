<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keeps the admin "update available" banner fresh without a web request
// touching GitHub. Requires the standard cron entry `* * * * * php artisan schedule:run`.
Schedule::command('sgcore:check-updates')->dailyAt('04:00');
