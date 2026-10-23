<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * Applies an update package onto the running installation, wrapped in
 * maintenance mode. Shared by the admin page, the scheduled auto-update and
 * anything else that already has a .zip on disk; the safety checks (manifest,
 * PHP version, protected paths, migrations) live in PackageUpdater.
 *
 * Maintenance mode is the fragile part on shared hosting: if the PHP process
 * dies mid-copy the `storage/framework/down` file stays behind and the site
 * serves 503 forever. Hence the lock file, the shutdown guard and the
 * self-healing `heal()` used by the Updates page.
 */
class PackageApply
{
    /** A lock younger than this means a real update is running. */
    private const STALE_MINUTES = 15;

    private bool $maintenance = false;

    public function __construct(private PackageUpdater $updater) {}

    /**
     * @return array{from:string,to:string,copied:int,skipped:int,migrate:int,clear:int}
     */
    public function apply(string $zipPath, string $from): array
    {
        if ($this->status()['busy']) {
            throw new RuntimeException('Já existe uma atualização em andamento.');
        }

        $this->prepare();
        $this->enterMaintenance($from);

        try {
            return $this->updater->apply(
                $zipPath,
                base_path(),
                (array) config('cms.packager.protected', ['.env', 'storage', 'bootstrap/cache']),
                $from,
            );
        } finally {
            $this->leaveMaintenance();
        }
    }

    /**
     * What the page needs to know: is the site down, is an update running and
     * since when. `stale` is true when maintenance is on but no lock backs it.
     *
     * @return array{down:bool,busy:bool,stale:bool,since:string,version:string,minutes:int}
     */
    public function status(): array
    {
        $down = is_file($this->downPath());
        $lock = $this->lock();
        $busy = $lock !== null;

        $since = $lock['started_at'] ?? ($down ? (int) @filemtime($this->downPath()) : 0);

        return [
            'down' => $down,
            'busy' => $down && $busy,
            'stale' => $down && ! $busy,
            'since' => $since > 0 ? Carbon::createFromTimestamp($since)->format('d/m/Y H:i') : '',
            'version' => (string) ($lock['version'] ?? ''),
            'minutes' => $since > 0 ? (int) floor((time() - $since) / 60) : 0,
        ];
    }

    /**
     * Turn the site back on. Safe to call when it is already up.
     */
    public function release(): bool
    {
        $wasDown = is_file($this->downPath());

        $this->leaveMaintenance();

        return $wasDown;
    }

    /**
     * Self-heal a maintenance mode that outlived its update (killed worker,
     * fatal error, proxy timeout). Only acts when no live lock exists, so it
     * never fights a real in-flight update.
     */
    public function heal(): bool
    {
        $status = $this->status();

        if (! $status['stale']) {
            return false;
        }

        $this->leaveMaintenance();

        return true;
    }

    private function prepare(): void
    {
        // The browser may hang up (or the proxy may timeout) before the copy
        // ends; the update must finish anyway or the site would stay down.
        ignore_user_abort(true);

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }

    private function enterMaintenance(string $version): void
    {
        File::ensureDirectoryExists(dirname($this->lockPath()));

        File::put($this->lockPath(), (string) json_encode([
            'pid' => getmypid(),
            'version' => $version,
            'started_at' => time(),
        ]));

        try {
            Artisan::call('down', ['--render' => 'errors::503', '--retry' => 60]);
        } catch (Throwable $e) {
            $this->forgetLock();

            throw $e;
        }

        $this->maintenance = true;

        // Last line of defence for fatal errors (memory, timeout inside the
        // copy): PHP may skip `finally`, but it still runs shutdown functions.
        register_shutdown_function(function (): void {
            if ($this->maintenance) {
                $this->leaveMaintenance();
            }
        });
    }

    private function leaveMaintenance(): void
    {
        $this->maintenance = false;

        try {
            $code = Artisan::call('up');
        } catch (Throwable) {
            $code = 1;
        }

        if ($code !== 0) {
            // `artisan up` needs a booted app; if that is what broke, drop the
            // marker files by hand instead of leaving the site at 503.
            File::delete([$this->downPath(), storage_path('framework/maintenance.php')]);
        }

        $this->forgetLock();
    }

    /**
     * @return array{pid?:int,version?:string,started_at?:int}|null
     */
    private function lock(): ?array
    {
        if (! is_file($this->lockPath())) {
            return null;
        }

        $raw = @file_get_contents($this->lockPath());
        $data = is_string($raw) ? json_decode($raw, true) : null;
        $started = is_array($data) ? (int) ($data['started_at'] ?? 0) : 0;

        if ($started <= 0 || (time() - $started) > self::STALE_MINUTES * 60) {
            return null;
        }

        return [
            'pid' => (int) ($data['pid'] ?? 0),
            'version' => (string) ($data['version'] ?? ''),
            'started_at' => $started,
        ];
    }

    private function forgetLock(): void
    {
        File::delete($this->lockPath());
    }

    private function lockPath(): string
    {
        return storage_path('framework/update.lock');
    }

    private function downPath(): string
    {
        return storage_path('framework/down');
    }
}
