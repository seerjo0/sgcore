<?php

use App\Modules\Themes\Http\Controllers\Admin\AppearanceController;
use App\Modules\Themes\Http\Controllers\Admin\ThemeContentController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-appearance')->group(function (): void {
    Route::prefix('aparencia')->name('admin.aparencia.')->group(function (): void {
        Route::get('/', [AppearanceController::class, 'index'])->name('index');
        Route::post('/', [AppearanceController::class, 'store'])->name('store');
        Route::post('/ativar', [AppearanceController::class, 'activate'])->name('ativar');

        Route::get('/conteudo', [ThemeContentController::class, 'global'])->name('conteudo');
        Route::get('/conteudo/pagina/{page}', [ThemeContentController::class, 'page'])->name('conteudo.pagina');
        Route::post('/conteudo', [ThemeContentController::class, 'saveGlobal'])->name('conteudo.salvar');
        Route::post('/conteudo/pagina/{page}', [ThemeContentController::class, 'savePage'])->name('conteudo.pagina.salvar');
    });
});
