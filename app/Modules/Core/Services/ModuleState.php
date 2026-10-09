<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\File;

/**
 * File-backed state of the module manager: which modules are inactive and
 * which uploads were installed. The file is a plain JSON document read at
 * bootstrap time (no database), so activation/deactivation apply to the
 * provider loader, the route files and the migration loading immediately.
 *
 * When the file does not exist every discovered module is active — the
 * exact behavior of the codebase before the module manager existed.
 */
class ModuleState
{
    public function __construct(private ?string $path = null) {}

    /**
     * @var array<string, mixed>|null
     */
    private ?array $cache = null;

    /**
     * Absolute path of the state file.
     */
    public function path(): string
    {
        return $this->path ?? (string) config(
            'cms.modules.state_file',
            env('CMS_MODULES_STATE_FILE', storage_path('app/modules.php')),
        );
    }

    /**
     * Whether the manager was ever used (a state file was written).
     */
    public function touched(): bool
    {
        return File::exists($this->path());
    }

    /**
     * Slugs that must not be loaded.
     *
     * @return list<string>
     */
    public function inactive(): array
    {
        return $this->read()['inactive'] ?? [];
    }

    public function isInactive(string $slug): bool
    {
        return in_array($slug, $this->inactive(), true);
    }

    /**
     * Persisted metadata of uploaded modules (slug => installed_at/version).
     *
     * @return array<string, array<string, mixed>>
     */
    public function installed(): array
    {
        return $this->read()['installed'] ?? [];
    }

    public function installedAt(string $slug): ?string
    {
        $meta = $this->installed();

        return isset($meta[$slug]['installed_at']) ? (string) $meta[$slug]['installed_at'] : null;
    }

    /**
     * Activate a module (remove it from the inactive list).
     */
    public function activate(string $slug): void
    {
        $this->saveWith($this->inactive(), fn (array $inactive): array => array_values(array_filter(
            $inactive,
            fn (string $candidate): bool => $candidate !== $slug,
        )));
    }

    /**
     * Deactivate a module (append it to the inactive list).
     */
    public function deactivate(string $slug): void
    {
        $inactive = $this->inactive();

        if (! in_array($slug, $inactive, true)) {
            $inactive[] = $slug;
        }

        $this->saveWith($inactive, null);
    }

    /**
     * Mark an uploaded module as installed.
     */
    public function rememberInstall(string $slug, string $version = ''): void
    {
        $installed = $this->installed();
        $installed[$slug] = [
            'installed_at' => now()->toIso8601String(),
            'version' => $version,
        ];

        $this->write(array_merge($this->read(), ['installed' => $installed]));
    }

    /**
     * Forget an uploaded module (used when it is deleted).
     */
    public function forgetModule(string $slug): void
    {
        $installed = $this->installed();
        unset($installed[$slug]);

        $this->write(array_merge($this->read(), ['installed' => $installed]));
    }

    /**
     * Drop the state file entirely (used by cms:uninstall).
     */
    public function forget(): void
    {
        File::delete($this->path());
    }

    /**
     * @return array{inactive?: list<string>, installed?: array<string, array<string, mixed>>}
     */
    private function read(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $path = $this->path();

        if (! is_file($path)) {
            return $this->cache = [];
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return $this->cache = [];
        }

        $payload = json_decode($raw, true);

        return $this->cache = is_array($payload) ? $payload : [];
    }

    private function saveWith(array $inactive, ?callable $mapInactive): void
    {
        $this->write(array_merge($this->read(), [
            'inactive' => $mapInactive === null ? $inactive : $mapInactive($inactive),
        ]));
    }

    private function write(array $payload): void
    {
        $path = $this->path();

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->cache = $payload;
    }
}
