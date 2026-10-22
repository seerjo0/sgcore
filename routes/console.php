<?php

use App\Modules\Core\Services\UpdatePolicy;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keeps the admin "update available" banner fresh without a web request
// touching GitHub. Requires the standard cron entry `* * * * * php artisan schedule:run`.
// The panel preference ("check automatically") decides whether it runs.
Schedule::command('sgcore:check-updates')
    ->dailyAt('04:00')
    ->when(fn (): bool => app(UpdatePolicy::class)->autoCheck());

// Download/install job driven by the panel preferences. Registered always so the
// toggles work without touching .env; the command exits early when auto-check is
// off, downloads only when asked to and installs only when explicitly enabled.
Schedule::command('sgcore:auto-update')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->when(fn (): bool => app(UpdatePolicy::class)->autoCheck());
