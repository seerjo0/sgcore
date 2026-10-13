<?php

use App\Modules\Blog\Http\Controllers\Admin\CategoryController;
use App\Modules\Blog\Http\Controllers\Admin\PostController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-blog')->group(function (): void {
    Route::prefix('posts')->name('admin.posts.')->group(function (): void {
        Route::get('/', [PostController::class, 'index'])->name('index');
        Route::get('/criar', [PostController::class, 'create'])->name('create');
        Route::post('/', [PostController::class, 'store'])->name('store');
        Route::get('/{post}/editar', [PostController::class, 'edit'])->name('edit');
        Route::put('/{post}', [PostController::class, 'update'])->name('update');
        Route::delete('/{post}', [PostController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('categorias')->name('admin.categorias.')->group(function (): void {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::get('/criar', [CategoryController::class, 'create'])->name('create');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::get('/{category}/editar', [CategoryController::class, 'edit'])->name('edit');
        Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
    });
});
