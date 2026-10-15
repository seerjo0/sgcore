<?php

use App\Modules\Core\Http\Controllers\Admin\DashboardController;
use App\Modules\Core\Http\Controllers\Admin\ModuleController;
use App\Modules\Core\Http\Controllers\Admin\SettingsController;
use App\Modules\Core\Http\Controllers\Admin\UpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

Route::middleware('can:manage-modules')->group(function (): void {
    Route::prefix('modulos')->name('admin.modulos.')->group(function (): void {
        Route::get('/', [ModuleController::class, 'index'])->name('index');
        Route::post('/', [ModuleController::class, 'store'])->name('store');
        Route::post('/{slug}/ativar', [ModuleController::class, 'activate'])->name('ativar');
        Route::post('/{slug}/desativar', [ModuleController::class, 'deactivate'])->name('desativar');
        Route::delete('/{slug}', [ModuleController::class, 'destroy'])->name('destroy');
    });
});

Route::middleware('can:manage-settings')->group(function (): void {
    Route::prefix('configuracoes')->name('admin.configuracoes.')->group(function (): void {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::post('/', [SettingsController::class, 'update'])->name('update');

        Route::post('/atualizacoes/verificar', [UpdateController::class, 'check'])
            ->middleware('throttle:10,1')
            ->name('atualizacoes.verificar');

        Route::post('/atualizacoes/descartar', [UpdateController::class, 'dismiss'])
            ->name('atualizacoes.descartar');

        Route::post('/atualizacoes/aplicar', [UpdateController::class, 'apply'])
            ->middleware('throttle:2,15')
            ->name('atualizacoes.aplicar');
    });
});
