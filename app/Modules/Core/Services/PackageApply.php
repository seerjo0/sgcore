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

    public function __construct(
        private PackageUpdater $updater,
        private ?PhpHandlerGuard $guard = null,
    ) {}

    /**
     * @param  (\Closure(array{stage:string,message:string,percent:int}):void)|null  $progress
     * @return array{from:string,to:string,copied:int,skipped:int,migrate:int,clear:int}
     */
    public function apply(string $zipPath, string $from, ?\Closure $progress = null): array
    {
        if ($this->status()['busy']) {
            throw new RuntimeException('Já existe uma atualização em andamento.');
        }

        $report = function (string $stage, string $message, int $percent) use ($progress): void {
            $progress?->__invoke(['stage' => $stage, 'message' => $message, 'percent' => $percent]);
        };

        $report('prepare', 'Preparando a atualização…', 2);

        $this->prepare();

        // Back up the host's PHP handler before any file is touched: a manual
        // extract later can be repaired from this copy.
        $this->guard()->remember(base_path());

        $report('maintenance', 'Ativando o modo de manutenção…', 6);

        $this->enterMaintenance($from);

        $report('maintenance', 'Site em manutenção. Aplicando o pacote…', 20);

        try {
            $result = $this->updater->apply(
                $zipPath,
                base_path(),
                (array) config('cms.packager.protected', ['.env', 'storage', 'bootstrap/cache']),
                $from,
                true,
                $progress,
            );

            if ($this->guard()->restore(base_path()) !== []) {
                $report('htaccess', 'Handler de PHP do cPanel restaurado.', 99);
            }

            return $result;
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

    /**
     * Put back a PHP handler wiped by a manual extraction. Returns the repaired
     * paths (empty when nothing was missing).
     *
     * @return list<string>
     */
    public function repairPhpHandler(): array
    {
        $guard = $this->guard();
        $guard->remember(base_path());

        return $guard->restore(base_path());
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
            // `--render` needs an existing view; errors::503 lives in
            // resources/views/errors since 1.2.2. Without it artisan prints an
            // error and returns 1 without activating maintenance, so the exit
            // code is checked instead of assumed.
            $code = Artisan::call('down', ['--render' => 'errors::503', '--retry' => 60]);

            if ($code !== 0 || ! is_file($this->downPath())) {
                throw new RuntimeException('Não foi possível ativar o modo de manutenção.');
            }
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

    private function guard(): PhpHandlerGuard
    {
        return $this->guard ??= app(PhpHandlerGuard::class);
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
