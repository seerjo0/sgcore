<?php

namespace App\Modules\Themes\Services;

/**
 * Immutable view of a theme found on disk (bundled or imported).
 */
class Theme
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $path,
        public readonly array $manifest,
        public readonly bool $builtin,
        public readonly bool $valid,
        public readonly ?string $error = null,
    ) {}

    public function name(): string
    {
        $name = $this->manifest['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : $this->slug;
    }

    public function description(): string
    {
        $description = $this->manifest['description'] ?? '';

        return is_string($description) ? $description : '';
    }

    public function version(): string
    {
        $version = $this->manifest['version'] ?? '';

        return is_string($version) ? $version : '';
    }

    /**
     * Template map (home/page/404) declared in theme.json.
     *
     * @return array<string, string>
     */
    public function templates(): array
    {
        $templates = $this->manifest['templates'] ?? [];

        return is_array($templates) ? $templates : [];
    }

    /**
     * Absolute path of a template key (home|page|404), or null if missing.
     */
    public function templatePath(string $key): ?string
    {
        $relative = $this->templates()[$key] ?? null;

        if (! is_string($relative) || $relative === '') {
            return null;
        }

        $full = $this->path.'/'.ltrim($relative, '/');

        return is_file($full) ? $full : null;
    }

    /**
     * Slot specs keyed by slot id.
     *
     * @return array<string, array<string, mixed>>
     */
    public function slots(): array
    {
        $slots = $this->manifest['slots'] ?? [];

        return is_array($slots) ? $slots : [];
    }

    /**
     * Menu locations provided by the theme: location => label.
     *
     * @return array<string, string>
     */
    public function menus(): array
    {
        $menus = $this->manifest['menus'] ?? [];

        return is_array($menus) ? $menus : [];
    }

    /**
     * Absolute path of the screenshot, when the file exists.
     */
    public function screenshotPath(): ?string
    {
        $relative = $this->manifest['screenshot'] ?? 'screenshot.png';

        if (! is_string($relative) || $relative === '') {
            return null;
        }

        $full = $this->path.'/'.ltrim($relative, '/');

        return is_file($full) ? $full : null;
    }
}
