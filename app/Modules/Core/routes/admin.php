<?php

use App\Modules\Core\Http\Controllers\Admin\DashboardController;
use App\Modules\Core\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

Route::middleware('can:manage-settings')->group(function (): void {
    Route::prefix('configuracoes')->name('admin.configuracoes.')->group(function (): void {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::post('/', [SettingsController::class, 'update'])->name('update');
    });
});
