<?php

namespace App\Modules\Installer\Http;

use Illuminate\Http\Request;

trait InteractsWithInstallerLocale
{
    private const AVAILABLE_LOCALES = ['pt' => 'pt_BR', 'en' => 'en'];

    /**
     * Resolve the wizard locale from the `lang` input, make it the current
     * request locale, and return the short token used in URLs and forms.
     */
    private function resolveWizardLocale(Request $request): string
    {
        $token = (string) $request->input('lang', '');

        if (! array_key_exists($token, self::AVAILABLE_LOCALES)) {
            $token = app()->getLocale() === 'en' ? 'en' : 'pt';
        }

        app()->setLocale(self::AVAILABLE_LOCALES[$token]);

        return $token;
    }
}
