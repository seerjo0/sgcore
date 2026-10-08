<?php

use App\Modules\Media\Http\Controllers\MediaFileController;
use Illuminate\Support\Facades\Route;

Route::get('/media/{path}', [MediaFileController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');
