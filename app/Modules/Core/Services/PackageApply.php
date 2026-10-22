<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\Artisan;

/**
 * Applies an update package onto the running installation, wrapped in
 * maintenance mode. Shared by the admin page, the scheduled auto-update and
 * anything else that already has a .zip on disk; the safety checks (manifest,
 * PHP version, protected paths, migrations) live in PackageUpdater.
 */
class PackageApply
{
    public function __construct(private PackageUpdater $updater) {}

    /**
     * @return array{from:string,to:string,copied:int,skipped:int,migrate:int,clear:int}
     */
    public function apply(string $zipPath, string $from): array
    {
        $secret = bin2hex(random_bytes(8));

        Artisan::call('down', ['--secret' => $secret, '--render' => 'errors::503']);

        try {
            return $this->updater->apply(
                $zipPath,
                base_path(),
                (array) config('cms.packager.protected', ['.env', 'storage', 'bootstrap/cache']),
                $from,
            );
        } finally {
            Artisan::call('up', ['--secret' => $secret]);
        }
    }
}
