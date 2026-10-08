<?php

use App\Modules\Pages\Http\Controllers\Admin\MenuController;
use App\Modules\Pages\Http\Controllers\Admin\PageController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-pages')->group(function (): void {
    Route::prefix('paginas')->name('admin.paginas.')->group(function (): void {
        Route::get('/', [PageController::class, 'index'])->name('index');
        Route::get('/criar', [PageController::class, 'create'])->name('create');
        Route::post('/', [PageController::class, 'store'])->name('store');
        Route::get('/{page}/editar', [PageController::class, 'edit'])->name('edit');
        Route::put('/{page}', [PageController::class, 'update'])->name('update');
        Route::delete('/{page}', [PageController::class, 'destroy'])->name('destroy');
    });
});

Route::middleware('can:manage-menus')->group(function (): void {
    Route::prefix('menus')->name('admin.menus.')->group(function (): void {
        Route::get('/', [MenuController::class, 'index'])->name('index');
        Route::get('/criar', [MenuController::class, 'create'])->name('create');
        Route::post('/', [MenuController::class, 'store'])->name('store');
        Route::get('/{menu}/editar', [MenuController::class, 'edit'])->name('edit');
        Route::put('/{menu}', [MenuController::class, 'update'])->name('update');
        Route::delete('/{menu}', [MenuController::class, 'destroy'])->name('destroy');

        Route::post('/{menu}/itens', [MenuController::class, 'storeItem'])->name('itens.store');

        Route::scopeBindings()->group(function (): void {
            Route::get('/{menu}/itens/{item}/editar', [MenuController::class, 'editItem'])->name('itens.edit');
            Route::put('/{menu}/itens/{item}', [MenuController::class, 'updateItem'])->name('itens.update');
            Route::match(['post', 'put'], '/{menu}/itens/{item}/mover', [MenuController::class, 'moveItem'])->name('itens.mover');
            Route::delete('/{menu}/itens/{item}', [MenuController::class, 'destroyItem'])->name('itens.destroy');
        });
    });
});
