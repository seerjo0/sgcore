<?php

namespace App\Modules\Core\Services;

/**
 * Admin display preferences (Configurações > Admin): accent color and
 * light/dark mode. Values live in the settings table but are always
 * validated against the whitelist in config('cms.admin.*').
 */
class AdminPreferences
{
    public const KEY_COLOR = 'admin_color';

    public const KEY_MODE = 'admin_mode';

    public const KEY_PATH = 'admin_path';

    /**
     * First path segments that must never be used as the admin prefix
     * (they would shadow public routes of the app). "admin" is allowed —
     * it is the default value.
     */
    public const RESERVED_PATHS = [
        'login',
        'logout',
        'instalar',
        'up',
        'media',
        'theme-assets',
        'storage',
        'build',
        'assets',
        'favicon.ico',
    ];

    public function __construct(private Settings $settings) {}

    /**
     * Available colors: slug => ['label' => …, 'hex' => …].
     *
     * @return array<string, array{label: string, hex: string}>
     */
    public function colors(): array
    {
        return config('cms.admin.colors', []);
    }

    /**
     * Available display modes: slug => label.
     *
     * @return array<string, string>
     */
    public function modes(): array
    {
        return config('cms.admin.modes', []);
    }

    /**
     * Current color slug (falls back to the default when unknown).
     */
    public function color(): string
    {
        return $this->whitelisted(
            (string) $this->settings->get(self::KEY_COLOR, config('cms.admin.default_color', 'azul')),
            array_keys($this->colors()),
            (string) config('cms.admin.default_color', 'azul'),
        );
    }

    /**
     * Current display mode slug (falls back to the default when unknown).
     */
    public function mode(): string
    {
        return $this->whitelisted(
            (string) $this->settings->get(self::KEY_MODE, config('cms.admin.default_mode', 'claro')),
            array_keys($this->modes()),
            (string) config('cms.admin.default_mode', 'claro'),
        );
    }

    public function isDark(): bool
    {
        return $this->mode() === 'escuro';
    }

    /**
     * URL prefix of the admin panel: setting `admin_path` wins, then the
     * env/config default (`cms.admin.default_path`), then "admin".
     *
     * Never throws: routes are loaded before the install runs, when the
     * settings table may not exist yet.
     */
    public function adminPath(): string
    {
        try {
            $stored = (string) ($this->settings->get(self::KEY_PATH, '') ?? '');
        } catch (\Throwable) {
            $stored = '';
        }

        return $this->sanitizePath($stored)
            ?? $this->sanitizePath((string) config('cms.admin.default_path', 'admin'))
            ?? 'admin';
    }

    /**
     * Normalize an admin path candidate; null when it is not acceptable.
     */
    public function sanitizePath(string $path): ?string
    {
        $path = strtolower(trim($path));

        if ($path === '' || strlen($path) > 30) {
            return null;
        }

        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $path) !== 1) {
            return null;
        }

        return in_array($path, self::RESERVED_PATHS, true) ? null : $path;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function whitelisted(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
