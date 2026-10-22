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

// Opt-in automatic update. Registers a schedule entry only when the operator
// enabled it (CMS_UPDATER_AUTO=true). The command itself still requires
// CMS_UPDATER_ALLOW_APPLY and delegates the swap to `sgcore:update --force`
// (maintenance + backup + migrate + rollback). Needs cron `schedule:run`.
if (config('cms.updater.auto.enabled')) {
    Schedule::command('sgcore:auto-update')
        ->hourly()
        ->withoutOverlapping()
        ->onOneServer()
        ->runInBackground();
}
