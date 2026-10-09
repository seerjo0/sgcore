<?php

use App\Modules\PageBuilder\Http\Controllers\Admin\PageBuilderController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-pages')->prefix('paginas')->name('admin.paginas.builder.')->group(function (): void {
    Route::get('/{page}/construtor', [PageBuilderController::class, 'edit'])->name('index');
    Route::post('/{page}/construtor/autosave', [PageBuilderController::class, 'autosave'])->name('autosave');
    Route::post('/{page}/construtor/publicar', [PageBuilderController::class, 'publish'])->name('publish');
});
