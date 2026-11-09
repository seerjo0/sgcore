<?php

use App\Modules\Auth\Http\Controllers\ForgotPasswordController;
use App\Modules\Auth\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::prefix(admin_path())->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.attempt');

        // "Forgot password" — the reset link e-mail is delivered through the
        // panel-managed SMTP; token routes live at the same (dynamic) path.
        // The reset token is a bcrypt hash (contains "/"), so it travels as a
        // QUERY parameter — {token} in the path breaks on nginx/Apache
        // (encoded slashes) and would leak through referrer headers on paths.
        Route::get('/esqueci-senha', [ForgotPasswordController::class, 'create'])->name('password.request');
        Route::post('/esqueci-senha', [ForgotPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
        Route::get('/redefinir-senha', [ForgotPasswordController::class, 'edit'])->name('password.reset');
        Route::post('/redefinir-senha', [ForgotPasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
});
