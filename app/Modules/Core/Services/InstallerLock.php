<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\File;

class InstallerLock
{
    /**
     * Absolute path of the installation lock file.
     */
    public function path(): string
    {
        return (string) config('cms.installer.lock_file');
    }

    /**
     * Whether the application has already been installed.
     */
    public function exists(): bool
    {
        return File::exists($this->path());
    }

    /**
     * Create the lock file, marking the installation as finished.
     *
     * @param  array<string, mixed>  $meta
     */
    public function create(array $meta = []): void
    {
        $payload = array_merge([
            'installed_at' => now()->toIso8601String(),
        ], $meta);

        File::ensureDirectoryExists(dirname($this->path()));
        File::put($this->path(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Remove the lock file, allowing a fresh installation.
     */
    public function forget(): void
    {
        File::delete($this->path());
    }
}
