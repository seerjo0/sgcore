<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Git-based "apply an update" runner. Assumes the installation is a Git clone
 * (sgcore is distributed that way): it fetches tags, checks out the target
 * tag, runs `composer install` and `php artisan migrate`, all wrapped in
 * maintenance mode with a best-effort DB backup and rollback.
 *
 * Every external command goes through an injected runner so the class can be
 * unit-tested without ever touching git, composer or the network. Applying is
 * disabled by default (see config `cms.updater.apply.enabled`).
 */
class UpdateRunner
{
    /**
     * @param  (callable(list<string>, string): array{0:int,1:string})|null  $runner  executes argv in $dir, returns [exitCode, output]
     */
    public function __construct(private ?\Closure $runner = null) {}

    public function isEnabled(): bool
    {
        return (bool) config('cms.updater.apply.enabled');
    }

    /**
     * A human-readable list of blockers that make auto-apply unsafe right now.
     *
     * @return list<string>
     */
    public function blockers(): array
    {
        $blockers = [];

        if (! $this->isEnabled()) {
            $blockers[] = 'auto-apply disabled';
        }

        if (! $this->isGitCheckout()) {
            $blockers[] = 'not a git checkout';
        }

        if (! $this->isTreeClean()) {
            $blockers[] = 'uncommitted changes';
        }

        return $blockers;
    }

    public function isGitCheckout(): bool
    {
        [$code] = $this->run([$this->git(), 'rev-parse', '--is-inside-work-tree']);

        return $code === 0;
    }

    public function isTreeClean(): bool
    {
        [$code, $out] = $this->run([$this->git(), 'status', '--porcelain']);

        return $code === 0 && trim($out) === '';
    }

    public function currentSha(): string
    {
        [$code, $out] = $this->run([$this->git(), 'rev-parse', 'HEAD']);

        return $code === 0 ? trim($out) : '';
    }

    public function currentTag(): string
    {
        foreach (['describe --tags --exact-match HEAD', 'describe --tags --always'] as $cmd) {
            [$code, $out] = $this->run(array_merge([$this->git()], explode(' ', $cmd)));

            if ($code === 0 && trim($out) !== '') {
                return trim($out);
            }
        }

        return '';
    }

    public function tagExists(string $tag): bool
    {
        [$code] = $this->run([$this->git(), 'rev-parse', '--verify', '--quiet', 'tags/'.$tag]);

        return $code === 0;
    }

    /**
     * One-line log of the commits the target tag would add on top of HEAD.
     */
    public function incomingCommits(string $tag, int $limit = 20): string
    {
        [$code, $out] = $this->run([
            $this->git(), 'log', '--oneline', '--no-decorate', '-n', (string) $limit,
            'HEAD..tags/'.$tag,
        ]);

        return $code === 0 ? trim($out) : '';
    }

    /**
     * Ordered list of steps an apply would execute, with the current state.
     *
     * @return list<array{key:string,label:string}>
     */
    public function plan(string $target): array
    {
        $steps = [
            ['key' => 'down', 'label' => 'Entrar em modo de manutenção (artisan down)'],
        ];

        if ($this->backupTool() !== null) {
            $steps[] = ['key' => 'backup', 'label' => 'Backup do banco via '.$this->backupTool()];
        } else {
            $steps[] = ['key' => 'backup', 'label' => 'Backup automático indisponível (nenhum mysqldump/pg_dump no PATH)'];
        }

        $steps[] = ['key' => 'fetch', 'label' => 'git fetch '.$this->remote().' --tags --prune'];
        $steps[] = ['key' => 'checkout', 'label' => 'git checkout '.$target];
        $steps[] = ['key' => 'composer', 'label' => 'composer install --no-dev --no-interaction --optimize-autoloader'];
        $steps[] = ['key' => 'migrate', 'label' => 'php artisan migrate --force'];
        $steps[] = ['key' => 'caches', 'label' => 'php artisan optimize:clear (cache, views, rotas, config)'];
        $steps[] = ['key' => 'up', 'label' => 'Sair do modo de manutenção (artisan up)'];

        return $steps;
    }

    /**
     * Applies the update. Throws RuntimeException on unrecoverable errors after
     * attempting a rollback. Callers should already have checked blockers().
     */
    public function apply(string $target, bool $withBackup = true): void
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('cms.updater.apply.enabled = false');
        }

        if (! $this->isGitCheckout() || ! $this->isTreeClean()) {
            throw new RuntimeException('aplicação recusada: working tree sujo ou não é git');
        }

        $previous = $this->currentSha();

        if ($previous === '') {
            throw new RuntimeException('não foi possível identificar HEAD');
        }

        $secret = bin2hex(random_bytes(8));

        $this->must(['artisan', 'down', '--secret='.$secret, '--render=errors::503']);

        $backup = null;

        try {
            $backup = $withBackup ? $this->backupDatabase() : null;

            $this->must([$this->git(), 'fetch', $this->remote(), '--tags', '--prune']);

            if (! $this->tagExists($target)) {
                throw new RuntimeException("tag {$target} não encontrada após fetch");
            }

            $this->must([$this->git(), 'checkout', '--detach', 'tags/'.$target]);

            $this->must([$this->composer(), 'install',
                '--no-dev', '--no-interaction', '--no-progress', '--optimize-autoloader',
            ]);

            $this->must(['artisan', 'migrate', '--force']);
            $this->must(['artisan', 'optimize:clear']);
        } catch (RuntimeException $e) {
            $this->rollback($previous, $backup);

            throw $e;
        }

        $this->run(['artisan', 'up', '--secret='.$secret]);
    }

    /**
     * Best-effort rollback: restore previous SHA, run composer install, restore
     * DB backup if we have one. Failures are swallowed so the caller still
     * sees the original error.
     */
    public function rollback(string $sha, ?string $backupFile): void
    {
        if ($sha !== '') {
            $this->run([$this->git(), 'checkout', '--detach', $sha]);
            $this->run([$this->composer(), 'install', '--no-dev', '--no-interaction', '--no-progress']);
        }

        if ($backupFile !== null && is_file($backupFile)) {
            $this->restoreDatabase($backupFile);
        }

        $this->run(['artisan', 'optimize:clear']);
    }

    private function backupTool(): ?string
    {
        $driver = (string) config('database.default');
        $connection = config("database.connections.{$driver}");

        if (! is_array($connection)) {
            return null;
        }

        return match ((string) ($connection['driver'] ?? '')) {
            'mysql', 'mariadb' => $this->which('mysqldump'),
            'pgsql' => $this->which('pg_dump'),
            'sqlite' => 'php', // special-cased below
            default => null,
        };
    }

    private function backupDatabase(): ?string
    {
        $dir = storage_path('app/backups');

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            return null;
        }

        $file = $dir.'/pre-update-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql';

        $driver = (string) config('database.default');
        $connection = (array) config("database.connections.{$driver}");
        $kind = (string) ($connection['driver'] ?? '');

        if ($kind === 'sqlite') {
            $path = (string) ($connection['database'] ?? '');

            if ($path !== '' && is_file($path) && @copy($path, $file)) {
                return $file;
            }

            return null;
        }

        $binary = $this->backupTool();

        if ($binary === null || $binary === 'php') {
            return null;
        }

        if ($kind === 'mysql' || $kind === 'mariadb') {
            $cmd = [
                $binary,
                '--single-transaction', '--quick',
                '-h', (string) ($connection['host'] ?? '127.0.0.1'),
                '-P', (string) ($connection['port'] ?? '3306'),
                '-u', (string) ($connection['username'] ?? ''),
            ];
            $env = [];

            if (($connection['password'] ?? '') !== '') {
                $env['MYSQL_PWD'] = (string) $connection['password'];
            }

            $cmd[] = '--databases';
            $cmd[] = (string) ($connection['database'] ?? '');

            return $this->redirect($cmd, $file, $env) ? $file : null;
        }

        if ($kind === 'pgsql') {
            $cmd = [
                $binary, '-h', (string) ($connection['host'] ?? '127.0.0.1'),
                '-p', (string) ($connection['port'] ?? '5432'),
                '-U', (string) ($connection['username'] ?? ''),
                '-f', $file,
                (string) ($connection['database'] ?? ''),
            ];
            $env = [];

            if (($connection['password'] ?? '') !== '') {
                $env['PGPASSWORD'] = (string) $connection['password'];
            }

            return $this->runEnv($cmd, $env)[0] === 0 ? $file : null;
        }

        return null;
    }

    private function restoreDatabase(string $file): void
    {
        $driver = (string) config('database.default');
        $connection = (array) config("database.connections.{$driver}");
        $kind = (string) ($connection['driver'] ?? '');

        if ($kind === 'sqlite') {
            $path = (string) ($connection['database'] ?? '');

            if ($path !== '' && is_file($file)) {
                @copy($file, $path);
            }

            return;
        }

        if ($kind === 'mysql' || $kind === 'mariadb') {
            $binary = $this->which('mysql');

            if ($binary !== null) {
                $this->pipe($file, [
                    $binary,
                    '-h', (string) ($connection['host'] ?? '127.0.0.1'),
                    '-u', (string) ($connection['username'] ?? ''),
                    (string) ($connection['database'] ?? ''),
                ]);
            }

            return;
        }
    }

    /**
     * @return array{0:int,1:string}
     */
    private function run(array $argv, array $env = []): array
    {
        return $this->runEnv($argv, $env);
    }

    /**
     * @param  array<string,string>  $env
     * @return array{0:int,1:string}
     */
    private function runEnv(array $argv, array $env): array
    {
        if ($argv !== [] && ($argv[0] === 'artisan' || $argv[0] === 'git' || $argv[0] === 'composer')) {
            $argv = $this->resolveBinary($argv);
        }

        if ($this->runner !== null) {
            return ($this->runner)($argv, $env);
        }

        $process = Process::path(base_path())->env($env)->timeout(600)->run($argv);

        return [$process->exitCode(), $process->output().$process->errorOutput()];
    }

    /**
     * @param  list<string>  $argv
     * @return list<string>
     */
    private function resolveBinary(array $argv): array
    {
        if ($argv[0] === 'artisan') {
            $argv[0] = (string) (PHP_BINARY ?: 'php');
            array_splice($argv, 1, 0, [base_path('artisan')]);
        }

        return $argv;
    }

    /**
     * @param  list<string>  $argv
     */
    private function must(array $argv): void
    {
        [$code, $out] = $this->runEnv($argv, []);

        if ($code !== 0) {
            throw new RuntimeException('comando falhou ('.$code.'): '.implode(' ', $argv)."\n".$out);
        }
    }

    /**
     * @param  array<string,string>  $env
     */
    private function redirect(array $argv, string $path, array $env = []): bool
    {
        if ($this->runner !== null) {
            return ($this->runner)(array_merge($argv, ['>', $path]), $env)[0] === 0;
        }

        $process = Process::path(base_path())->env($env)->timeout(600)->run($argv, function ($type, $buffer) use ($path) {
            if ($type === 'out') {
                @file_put_contents($path, $buffer, FILE_APPEND);
            }
        });

        return $process->successful();
    }

    /**
     * @param  list<string>  $argv
     */
    private function pipe(string $inputFile, array $argv): array
    {
        if ($this->runner !== null) {
            return ($this->runner)(array_merge($argv, ['<', $inputFile]), []);
        }

        $process = Process::path(base_path())->timeout(600)
            ->input(fopen($inputFile, 'r') ?: '')
            ->run($argv);

        return [$process->exitCode(), $process->output().$process->errorOutput()];
    }

    private function which(string $binary): ?string
    {
        [$code, $out] = $this->runEnv(
            PHP_OS_FAMILY === 'Windows' ? ['where', $binary] : ['which', $binary],
            []
        );

        if ($code !== 0) {
            return null;
        }

        $first = trim(strtok($out, "\n"));

        return $first !== '' ? $first : null;
    }

    private function git(): string
    {
        return (string) config('cms.updater.apply.git', 'git');
    }

    private function composer(): string
    {
        return (string) config('cms.updater.apply.composer', 'composer');
    }

    private function remote(): string
    {
        return (string) config('cms.updater.apply.remote', 'origin');
    }
}
