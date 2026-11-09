<?php

use App\Modules\Core\Http\Controllers\Site\ContactFormController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site endpoints contributed by Core
|--------------------------------------------------------------------------
|
| Loaded inside the `web` group (session + CSRF) by routes/web.php. The Themes
| module owns "/" and the catch-all fallback; this is a fixed POST route, so
| it never conflicts with page slugs (slug validation already reserves the
| first segment anyway).
|
*/

Route::post('/contato/enviar', [ContactFormController::class, 'store'])
    ->middleware('throttle:8,1')
    ->name('site.contato.enviar');
