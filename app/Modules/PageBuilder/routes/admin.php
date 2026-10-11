<?php

use App\Modules\PageBuilder\Http\Controllers\Admin\BuilderTemplateController;
use App\Modules\PageBuilder\Http\Controllers\Admin\PageBuilderController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-pages')->prefix('paginas')->name('admin.paginas.builder.')->group(function (): void {
    Route::get('/{page}/construtor', [PageBuilderController::class, 'edit'])->name('index');
    Route::post('/{page}/construtor/autosave', [PageBuilderController::class, 'autosave'])->name('autosave');
    Route::post('/{page}/construtor/publicar', [PageBuilderController::class, 'publish'])->name('publish');
    Route::get('/{page}/construtor/revisoes', [PageBuilderController::class, 'revisions'])->name('revisions');
    Route::get('/{page}/construtor/revisoes/{revision}/diferencas', [PageBuilderController::class, 'revisionDiff'])->name('revisions.diff');
    Route::post('/{page}/construtor/revisoes/{revision}/restaurar', [PageBuilderController::class, 'restoreRevision'])->name('revisions.restore');
});

Route::middleware('can:manage-pages')->prefix('construtor/modelos')->name('admin.builder.templates.')->group(function (): void {
    Route::get('/', [BuilderTemplateController::class, 'index'])->name('index');
    Route::post('/identidade', [BuilderTemplateController::class, 'designSave'])->name('design');
    Route::get('/{type}', [BuilderTemplateController::class, 'edit'])->name('edit');
    Route::post('/{type}/autosave', [BuilderTemplateController::class, 'autosave'])->name('autosave');
    Route::post('/{type}/publicar', [BuilderTemplateController::class, 'publish'])->name('publish');
    Route::post('/{type}/alternar', [BuilderTemplateController::class, 'toggle'])->name('toggle');
});
