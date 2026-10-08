<?php

use App\Modules\Themes\Http\Controllers\SiteController;
use App\Modules\Themes\Http\Controllers\ThemeAssetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site (rendered by the active theme)
|--------------------------------------------------------------------------
|
| Route::fallback is matched only when NO other route did (any registration
| order), so /media, /admin, /instalar, /up, /storage always win.
|
*/

Route::get('/', [SiteController::class, 'home'])->name('site.home');

Route::get('/theme-assets/{theme}/{file}', [ThemeAssetController::class, 'show'])
    ->where('file', '.*')
    ->name('theme.assets');

Route::fallback([SiteController::class, 'fallback'])->name('site.fallback');
