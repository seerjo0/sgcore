<?php

use Illuminate\Support\Facades\Route;

/**
 * Resolve route files contributed by modules for the given group.
 *
 * @return array<int, string>
 */
$moduleRoutes = function (string $group): array {
    $files = glob(app_path("Modules/*/routes/{$group}.php")) ?: [];
    sort($files);

    return $files;
};

/*
|--------------------------------------------------------------------------
| Public site (frontend rendered by the active theme)
|--------------------------------------------------------------------------
|
| The Themes module contributes "/" and a Route::fallback (matched only when
| no other route did) so it never shadows /media, {admin_path}, /instalar, /up…
|
*/
Route::middleware('web')->group(function () use ($moduleRoutes): void {
    foreach ($moduleRoutes('web') as $file) {
        require $file;
    }
});

/*
|--------------------------------------------------------------------------
| Guest-facing authentication routes (login) — at {admin_path}/login
|--------------------------------------------------------------------------
*/
Route::middleware('web')->group(function () use ($moduleRoutes): void {
    foreach ($moduleRoutes('auth') as $file) {
        require $file;
    }
});

/*
|--------------------------------------------------------------------------
| Admin panel (requires an authenticated user) — at {admin_path}/…
|--------------------------------------------------------------------------
|
| The prefix comes from the `admin_path` setting (see AdminPreferences) so
| the panel can be moved (e.g. /backend) for security. Route names are
| unaffected; links generated via route('admin.…') follow the setting.
|
*/
Route::middleware(['web', 'auth', 'active'])->prefix(admin_path())->group(function () use ($moduleRoutes): void {
    foreach ($moduleRoutes('admin') as $file) {
        require $file;
    }
});
