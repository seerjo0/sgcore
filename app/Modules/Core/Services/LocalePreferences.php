<?php

namespace App\Modules\Core\Services;

/**
 * Site language chosen in Configurações > Idioma. The value lives in the
 * settings table but is always validated against the whitelist in
 * config('cms.locales'); the fallback is the boot locale (APP_LOCALE).
 */
class LocalePreferences
{
    public const KEY_LOCALE = 'site_locale';

    public function __construct(private Settings $settings) {}

    /**
     * Available locales: code => native label (as written by the users of
     * that language, so it must not be translated).
     *
     * @return array<string, string>
     */
    public function available(): array
    {
        return config('cms.locales', []);
    }

    /**
     * Valid stored locale code, or null when none (or an invalid one) is
     * stored — in that case the framework keeps config('app.locale').
     *
     * Never throws: resolved for every web request, including before the
     * install runs when the settings table may not exist yet.
     */
    public function stored(): ?string
    {
        try {
            $stored = (string) ($this->settings->get(self::KEY_LOCALE, '') ?? '');
        } catch (\Throwable) {
            return null;
        }

        return in_array($stored, array_keys($this->available()), true) ? $stored : null;
    }

    /**
     * Current site locale code, always one of the available locales.
     */
    public function locale(): string
    {
        return $this->stored() ?? (string) config('app.locale', 'pt_BR');
    }
}
