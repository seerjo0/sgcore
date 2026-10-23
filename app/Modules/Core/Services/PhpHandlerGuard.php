<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\File;

/**
 * Keeps a copy of the host's PHP handler directive (cPanel/MultiPHP block or a
 * bare AddHandler line) so it can be put back after an update replaces
 * .htaccess. The packaged updater already merges blocks, but a manual extract
 * through the File Manager overwrites the file without running any PHP code —
 * this guard repairs it the next time an admin opens the Updates page (or runs
 * an update), so the domain stops falling back to the account's default PHP.
 */
class PhpHandlerGuard
{
    /**
     * @return array<string,string> relative path => handler directive
     */
    public function handlerFiles(string $root): array
    {
        $found = [];

        foreach (['.htaccess', 'public/.htaccess'] as $relative) {
            $path = $this->path($root, $relative);

            if (! is_file($path)) {
                continue;
            }

            $block = HtaccessRules::handler((string) file_get_contents($path));

            if ($block !== '') {
                $found[$relative] = $block;
            }
        }

        return $found;
    }

    /**
     * Persist whatever handler the live files carry. Older entries are kept, so
     * a temporarily missing block (mid-extract) does not erase the backup.
     */
    public function remember(string $root): void
    {
        $found = $this->handlerFiles($root);

        if ($found === []) {
            return;
        }

        $this->write(array_merge($this->stored(), $found));
    }

    /**
     * Re-insert a stored handler into files that lost it.
     *
     * @return list<string> relative paths repaired
     */
    public function restore(string $root): array
    {
        $repaired = [];

        foreach ($this->stored() as $relative => $block) {
            $path = $this->path($root, $relative);

            if (! is_file($path) || $block === '') {
                continue;
            }

            $current = (string) file_get_contents($path);

            if (HtaccessRules::handler($current) !== '') {
                continue;
            }

            $merged = rtrim($current)."\n\n".$block."\n";

            if (@file_put_contents($path, $merged) !== false) {
                $repaired[] = $relative;
            }
        }

        return $repaired;
    }

    /**
     * Paths that have a stored handler but no handler in the live file.
     *
     * @return list<string>
     */
    public function missing(string $root): array
    {
        $missing = [];

        foreach ($this->stored() as $relative => $block) {
            $path = $this->path($root, $relative);

            if ($block === '' || ! is_file($path)) {
                continue;
            }

            if (HtaccessRules::handler((string) file_get_contents($path)) === '') {
                $missing[] = $relative;
            }
        }

        return $missing;
    }

    /**
     * @return array<string,string>
     */
    private function stored(): array
    {
        $path = $this->storePath();

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) @file_get_contents($path), true);

        if (! is_array($data)) {
            return [];
        }

        return array_filter($data, static fn ($block): bool => is_string($block) && $block !== '');
    }

    /**
     * @param  array<string,string>  $handlers
     */
    private function write(array $handlers): void
    {
        File::ensureDirectoryExists(dirname($this->storePath()));

        @file_put_contents(
            $this->storePath(),
            (string) json_encode($handlers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    private function path(string $root, string $relative): string
    {
        return rtrim($root, '/\\').'/'.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function storePath(): string
    {
        return storage_path('framework/php-handlers.json');
    }
}
