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

        Route::get('/atualizacoes', [UpdateController::class, 'index'])
            ->name('atualizacoes.index');

        Route::post('/atualizacoes/manutencao/sair', [UpdateController::class, 'releaseMaintenance'])
            ->name('atualizacoes.manutencao.sair');

        Route::post('/atualizacoes/verificar', [UpdateController::class, 'check'])
            ->name('atualizacoes.verificar');

        Route::post('/atualizacoes/descartar', [UpdateController::class, 'dismiss'])
            ->name('atualizacoes.descartar');

        Route::post('/atualizacoes/aplicar', [UpdateController::class, 'apply'])
            ->name('atualizacoes.aplicar');

        Route::post('/atualizacoes/pacote', [UpdateController::class, 'applyPackage'])
            ->name('atualizacoes.pacote');

        Route::post('/atualizacoes/config', [UpdateController::class, 'settings'])
            ->name('atualizacoes.config');

        Route::post('/atualizacoes/baixar', [UpdateController::class, 'download'])
            ->name('atualizacoes.baixar');

        Route::post('/atualizacoes/instalar', [UpdateController::class, 'install'])
            ->name('atualizacoes.instalar');

        Route::post('/atualizacoes/pacote/descartar', [UpdateController::class, 'discardPackage'])
            ->name('atualizacoes.pacote.descartar');
    });
});
