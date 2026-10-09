<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Services\LocalePreferences;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the site language chosen in Configurações > Idioma to every web
 * request (public site, login and admin panel). Falls back to APP_LOCALE
 * when no (or an invalid) value is stored.
 */
class SetLocale
{
    public function __construct(private LocalePreferences $preferences) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only an explicitly stored value overrides the locale; otherwise the
        // framework keeps config('app.locale') (APP_LOCALE) untouched.
        $locale = $this->preferences->stored();

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
