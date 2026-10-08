<?php

use App\Modules\Media\Http\Controllers\Admin\GalleryController;
use App\Modules\Media\Http\Controllers\Admin\MediaController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-media')->group(function (): void {
    Route::prefix('midia')->name('admin.midia.')->group(function (): void {
        Route::get('/', [MediaController::class, 'index'])->name('index');
        Route::post('/', [MediaController::class, 'store'])->name('store');
        Route::get('/buscar', [MediaController::class, 'buscar'])->name('buscar');
        Route::get('/{medium}/editar', [MediaController::class, 'edit'])->name('edit');
        Route::put('/{medium}', [MediaController::class, 'update'])->name('update');
        Route::delete('/{medium}', [MediaController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('galerias')->name('admin.galerias.')->group(function (): void {
        Route::get('/', [GalleryController::class, 'index'])->name('index');
        Route::get('/criar', [GalleryController::class, 'create'])->name('create');
        Route::post('/', [GalleryController::class, 'store'])->name('store');
        Route::get('/{gallery}/editar', [GalleryController::class, 'edit'])->name('edit');
        Route::put('/{gallery}', [GalleryController::class, 'update'])->name('update');
        Route::delete('/{gallery}', [GalleryController::class, 'destroy'])->name('destroy');

        Route::post('/{gallery}/itens', [GalleryController::class, 'storeItem'])->name('itens.store');

        Route::scopeBindings()->group(function (): void {
            Route::put('/{gallery}/itens/{item}', [GalleryController::class, 'updateItem'])->name('itens.update');
            Route::match(['post', 'put'], '/{gallery}/itens/{item}/mover', [GalleryController::class, 'moveItem'])->name('itens.mover');
            Route::delete('/{gallery}/itens/{item}', [GalleryController::class, 'destroyItem'])->name('itens.destroy');
        });
    });
});
