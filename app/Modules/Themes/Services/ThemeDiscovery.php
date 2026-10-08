<?php

namespace App\Modules\Themes\Services;

use App\Modules\Core\Services\Settings;
use Illuminate\Support\Collection;

/**
 * Scans the bundled and imported theme directories and builds Theme views.
 */
class ThemeDiscovery
{
    public function __construct(private ThemeManifest $manifest) {}

    /**
     * All themes found on disk (bundled first), including invalid ones so the
     * admin can show why a theme cannot be activated.
     *
     * @return Collection<string, Theme>
     */
    public function all(): Collection
    {
        $themes = collect();

        foreach ([$this->builtinPath(), $this->storagePath()] as $location) {
            if (! is_dir($location)) {
                continue;
            }

            foreach (scandir($location) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                    continue;
                }

                $path = $location.'/'.$entry;

                if (! is_dir($path)) {
                    continue;
                }

                $theme = $this->make($entry, $path, $location === $this->builtinPath());
                $themes->put($theme->slug, $theme);
            }
        }

        return $themes;
    }

    /**
     * Valid (activatable) themes only.
     *
     * @return Collection<string, Theme>
     */
    public function activatable(): Collection
    {
        return $this->all()->filter(fn (Theme $theme): bool => $theme->valid);
    }

    public function find(string $slug): ?Theme
    {
        return $this->all()->get($slug);
    }

    /**
     * The theme that should render the site right now.
     */
    public function active(): ?Theme
    {
        $active = (string) app(Settings::class)
            ->get('active_theme', config('cms.themes.default_theme', 'classic'));

        $theme = $this->find($active);

        if ($theme !== null && $theme->valid) {
            return $theme;
        }

        return $this->activatable()->first();
    }

    public function builtinPath(): string
    {
        return rtrim((string) config('cms.themes.builtin_path'), '/\\');
    }

    public function storagePath(): string
    {
        return rtrim((string) config('cms.themes.storage_path'), '/\\');
    }

    private function make(string $slug, string $path, bool $builtin): Theme
    {
        [$manifest, $error] = $this->manifest->load($path);

        return new Theme(
            slug: $slug,
            path: $path,
            manifest: $manifest ?? [],
            builtin: $builtin,
            valid: $error === null,
            error: $error,
        );
    }
}
