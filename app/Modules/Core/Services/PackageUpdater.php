<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * WordPress-style updater: takes a package .zip (the same artifact that
 * sgcore:package builds and a GitHub Release attaches) and replaces the running
 * installation's files in place, then runs pending migrations.
 *
 * Nothing is executed from the web server except the PHP process calling artisan
 * commands, so it works on shared hosting that has no git/composer/ssh. Files
 * under the manifest's `protected` list (.env, storage, bootstrap/cache) are
 * never overwritten, so configuration and user data survive.
 *
 * The command runner is injectable so the whole flow can be tested without a
 * real migration; the default runner calls Artisan in-process.
 */
class PackageUpdater
{
    /**
     * @param  (callable(string): int)|null  $commandRunner  runs an artisan command, returns exit code
     */
    public function __construct(private ?\Closure $commandRunner = null) {}

    /**
     * Inspect a package without writing anything.
     *
     * @return array{name:string,version:string,php_min:string}
     */
    public function inspect(string $zipPath): array
    {
        $manifest = $this->readManifest($zipPath);

        return [
            'name' => (string) ($manifest['name'] ?? ''),
            'version' => ltrim((string) ($manifest['version'] ?? ''), 'vV'),
            'php_min' => (string) ($manifest['php_min'] ?? '8.3'),
        ];
    }

    /**
     * Apply the package onto $targetRoot.
     *
     * @param  list<string>  $protected  relative paths never to overwrite
     * @return array{from:string,to:string,copied:int,skipped:int,migrate:int,clear:int}
     */
    public function apply(string $zipPath, string $targetRoot, array $protected, string $currentVersion, bool $runCommands = true): array
    {
        $manifest = $this->readManifest($zipPath);

        if (($manifest['name'] ?? null) !== 'sgcore') {
            throw new RuntimeException('Pacote inválido: manifest sem name=sgcore.');
        }

        $newVersion = ltrim((string) ($manifest['version'] ?? ''), 'vV');
        $phpMin = (string) ($manifest['php_min'] ?? '8.3');

        if ($phpMin !== '' && version_compare(PHP_VERSION, $phpMin, '<')) {
            throw new RuntimeException("Este pacote exige PHP {$phpMin}+. O servidor tem ".PHP_VERSION.'.');
        }

        $extractTo = $this->extract($zipPath);

        try {
            if (! $this->looksLikeApp($extractTo)) {
                throw new RuntimeException('Pacote incompleto: faltam artisan/vendor.');
            }

            $copy = $this->copyTree($extractTo, $targetRoot, $protected);

            $migrate = 0;
            $clear = 0;

            if ($runCommands) {
                $migrate = ($this->runner())('migrate --force');

                if ($migrate !== 0) {
                    throw new RuntimeException('As migrations falharam após copiar os arquivos (exit '.$migrate.').');
                }

                $clear = ($this->runner())('optimize:clear');
            }

            return [
                'from' => $currentVersion,
                'to' => $newVersion,
                'copied' => $copy['copied'],
                'skipped' => $copy['skipped'],
                'migrate' => $migrate,
                'clear' => $clear,
            ];
        } finally {
            $this->removeDir($extractTo);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function readManifest(string $zipPath): array
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException('Arquivo de pacote não encontrado.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensão PHP zip é necessária.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Não foi possível abrir o .zip (corrompido ou não é um zip).');
        }

        try {
            $raw = $zip->getFromName('sgcore-package.json');
        } finally {
            $zip->close();
        }

        if (! is_string($raw)) {
            throw new RuntimeException('Pacote inválido: sem sgcore-package.json.');
        }

        $manifest = json_decode($raw, true);

        if (! is_array($manifest)) {
            throw new RuntimeException('Manifesto inválido no pacote.');
        }

        return $manifest;
    }

    private function extract(string $zipPath): string
    {
        $temp = sys_get_temp_dir().'/sgcore-pkg-'.bin2hex(random_bytes(6));

        if (! mkdir($temp, 0775, true) && ! is_dir($temp)) {
            throw new RuntimeException('Falha ao criar diretório temporário.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true || ! $zip->extractTo($temp)) {
            $zip->close();
            $this->removeDir($temp);

            throw new RuntimeException('Falha ao extrair o pacote.');
        }

        $zip->close();

        return $temp;
    }

    /** @param array<string,int> $counters */
    private function looksLikeApp(string $dir): bool
    {
        return is_file($dir.'/artisan')
            && is_file($dir.'/composer.json')
            && is_dir($dir.'/vendor');
    }

    /**
     * @param  list<string>  $protected
     * @return array{copied:int,skipped:int}
     */
    private function copyTree(string $src, string $dest, array $protected): array
    {
        $copied = 0;
        $skipped = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $srcLen = strlen($src) + 1;

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), $srcLen));

            if ($this->isProtected($relative, $protected)) {
                $skipped++;

                continue;
            }

            $target = $dest.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0775, true);
                }

                continue;
            }

            $dir = dirname($target);

            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            if (! copy($item->getPathname(), $target)) {
                throw new RuntimeException("Falha ao copiar {$relative}.");
            }

            $copied++;
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    /**
     * @param  list<string>  $protected
     */
    private function isProtected(string $relative, array $protected): bool
    {
        foreach ($protected as $path) {
            $needle = rtrim(str_replace('\\', '/', $path), '/');

            if ($relative === $needle || str_starts_with($relative, $needle.'/')) {
                return true;
            }
        }

        return false;
    }

    private function runner(): \Closure
    {
        return $this->commandRunner ?? function (string $command): int {
            try {
                return Artisan::call(trim(explode(' ', $command, 2)[0]), $this->parseArgs($command));
            } catch (Throwable) {
                return 1;
            }
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function parseArgs(string $command): array
    {
        $parts = preg_split('/\s+/', trim($command)) ?: [];
        array_shift($parts);

        $args = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) {
                $flag = substr($part, 2);
                $args[$flag] = str_contains($flag, '=') ? (explode('=', $flag, 2)[1]) : true;
            }
        }

        return $args;
    }

    private function removeDir(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path.DIRECTORY_SEPARATOR.$entry;

            is_dir($full) && ! is_link($full) ? $this->removeDir($full) : @unlink($full);
        }

        @rmdir($path);
    }
}
