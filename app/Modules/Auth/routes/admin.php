<?php

use App\Modules\Auth\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:admin')->prefix('usuarios')->name('admin.usuarios.')->group(function (): void {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/criar', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}/editar', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
});
