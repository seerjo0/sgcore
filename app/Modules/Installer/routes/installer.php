<?php

use App\Modules\Installer\Http\Controllers\DatabaseController;
use App\Modules\Installer\Http\Controllers\RequirementsController;
use App\Modules\Installer\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/instalar', [RequirementsController::class, 'index'])
    ->name('installer.requirements');

Route::get('/instalar/banco', [DatabaseController::class, 'index'])
    ->name('installer.database');
Route::post('/instalar/banco', [DatabaseController::class, 'store'])
    ->name('installer.database.store');

Route::get('/instalar/site', [SiteController::class, 'index'])
    ->name('installer.site');
Route::post('/instalar/site', [SiteController::class, 'store'])
    ->name('installer.site.store');

Route::get('/instalar/concluido', [SiteController::class, 'done'])
    ->name('installer.done');
