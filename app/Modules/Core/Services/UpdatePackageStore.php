<?php

namespace App\Modules\Core\Services;

use RuntimeException;

/**
 * One-slot shelf for the release .zip that was downloaded ahead of time.
 *
 * The admin page (or the scheduled job) can download a package, inspect it and
 * install it later, without a second download. Files live in storage/app/updates,
 * which the updater never overwrites, so the package survives the install it is
 * about to perform (even though the code around it changes).
 */
class UpdatePackageStore
{
    /**
     * Directory holding the downloaded package and its metadata.
     */
    public function dir(): string
    {
        return storage_path('app/updates');
    }

    /**
     * Metadata of the stored package, or null when there is none. A package
     * whose .zip vanished is ignored (and dropped from the shelf).
     *
     * @return array{version:string,name:string,file:string,bytes:int,sha256:string,downloaded_at:string,path:string}|null
     */
    public function current(): ?array
    {
        $best = null;

        foreach (glob($this->dir().'/*.json') ?: [] as $metaPath) {
            $meta = json_decode((string) @file_get_contents($metaPath), true);

            if (! is_array($meta) || ! isset($meta['version'], $meta['file'])) {
                continue;
            }

            $path = $this->dir().DIRECTORY_SEPARATOR.basename((string) $meta['file']);

            if (! is_file($path)) {
                @unlink($metaPath);

                continue;
            }

            $meta['path'] = $path;
            $meta['bytes'] = (int) ($meta['bytes'] ?? filesize($path));
            $meta['sha256'] = (string) ($meta['sha256'] ?? '');
            $meta['downloaded_at'] = (string) ($meta['downloaded_at'] ?? '');
            $meta['name'] = (string) ($meta['name'] ?? basename($path));

            if ($best === null || strtotime($meta['downloaded_at']) >= strtotime($best['downloaded_at'])) {
                $best = $meta;
            }
        }

        return $best;
    }

    /**
     * Local path of the stored package, if it still exists.
     */
    public function path(): ?string
    {
        return $this->current()['path'] ?? null;
    }

    /**
     * Move a freshly downloaded .zip onto the shelf, replacing any previous one.
     *
     * @return array{version:string,name:string,file:string,bytes:int,sha256:string,downloaded_at:string,path:string}
     */
    public function store(string $zipPath, string $version, string $name = ''): array
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException('Pacote baixado não encontrado.');
        }

        $version = trim($version) !== '' ? trim($version) : 'unknown';
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $version) ?: 'unknown';
        $file = "sgcore-{$slug}.zip";
        $dir = $this->dir();

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar a pasta de pacotes baixados.');
        }

        $this->discard();

        $dest = $dir.DIRECTORY_SEPARATOR.$file;

        if (! @rename($zipPath, $dest) && ! @copy($zipPath, $dest)) {
            throw new RuntimeException('Não foi possível guardar o pacote baixado.');
        }

        $meta = [
            'version' => $version,
            'name' => $name !== '' ? $name : $file,
            'file' => $file,
            'bytes' => (int) @filesize($dest),
            'sha256' => (string) @hash_file('sha256', $dest),
            'downloaded_at' => now()->toIso8601String(),
        ];

        @file_put_contents(
            $dir.DIRECTORY_SEPARATOR."sgcore-{$slug}.json",
            (string) json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        return $meta + ['path' => $dest];
    }

    /**
     * Delete the stored package (zip + metadata). Returns whether anything went.
     */
    public function discard(): bool
    {
        $removed = false;

        foreach (glob($this->dir().'/sgcore-*') ?: [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $removed = true;
            }
        }

        return $removed;
    }
}
