<?php

use App\Modules\Auth\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::prefix(admin_path())->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.attempt');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
});
