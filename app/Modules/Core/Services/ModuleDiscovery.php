<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Collection;

/**
 * Discovers modules on disk: bundled ones under app/Modules plus uploaded
 * ones under cms.modules.storage_path. A folder is a module when it holds a
 * module.json and/or a Providers/*ServiceProvider.php file.
 */
class ModuleDiscovery
{
    public function __construct(
        private ModuleManifest $manifest,
        private ModuleState $state,
    ) {}

    public function storagePath(): string
    {
        return (string) config(
            'cms.modules.storage_path',
            env('CMS_MODULES_STORAGE_PATH', storage_path('app/modules')),
        );
    }

    /**
     * Base directories scanned for modules.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return [app_path('Modules'), $this->storagePath()];
    }

    /**
     * @return Collection<int, Module>
     */
    public function all(): Collection
    {
        $modules = collect();

        foreach ($this->paths() as $base) {
            if (! is_dir($base)) {
                continue;
            }

            foreach (scandir($base) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                    continue;
                }

                $path = $base.'/'.$entry;

                if (! is_dir($path)) {
                    continue;
                }

                $modules->push($this->build($path));
            }
        }

        return $modules
            ->filter()
            ->sortBy(fn (Module $module): string => $module->slug)
            ->values();
    }

    public function find(string $slug): ?Module
    {
        return $this->all()->first(
            fn (Module $module): bool => $module->slug === $slug,
        );
    }

    /**
     * Modules that are unknown to the manager (no state metadata, e.g. a
     * just-uploaded module) default to active unless declared inactive.
     */
    public function isActive(string $slug): bool
    {
        return ! $this->state->isInactive($slug);
    }

    private function build(string $path): ?Module
    {
        [$manifest, $manifestError] = $this->manifest->load($path);

        $providerPresent = glob(rtrim($path, '/\\').'/Providers/*ServiceProvider.php') !== [];

        if ($manifest === null && $manifestError !== null && ! $providerPresent) {
            return null; // neither a manifest nor a provider → not a module
        }

        $slug = Module::slugFrom(basename($path));

        if (is_array($manifest) && isset($manifest['slug'])) {
            $slug = (string) $manifest['slug'];
        }

        $requires = $manifest['requires'] ?? [];

        if (! is_array($requires)) {
            $requires = [];
        }

        $error = null;

        if ($manifestError !== null) {
            $error = $manifestError;
        } elseif (! $providerPresent) {
            $error = 'O módulo não possui um Providers/*ServiceProvider.php.';
        }

        return new Module(
            slug: $slug,
            path: $path,
            manifest: is_array($manifest) ? $manifest : [],
            source: str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', app_path('Modules')))
                ? Module::BUNDLED
                : Module::UPLOADED,
            valid: $manifestError === null && $providerPresent,
            error: $error,
            requires: $requires,
            installedAt: $this->state->installedAt($slug),
        );
    }
}
