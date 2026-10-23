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
 * .htaccess is a special case: the package ships its rewrite rules, but the
 * live file also holds host-generated directives (cPanel's PHP handler). Those
 * files are merged block by block instead of copied over.
 *
 * The command runner is injectable so the whole flow can be tested without a
 * real migration; the default runner calls Artisan in-process.
 */
class PackageUpdater
{
    /** Files that mix project rules with host (cPanel) directives. */
    private const HTACCESS_FILES = ['.htaccess', 'public/.htaccess'];

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
     * `$progress` receives one array per step: ['stage' => string, 'message' =>
     * string, 'percent' => int]. The package flow uses it to stream the terminal
     * on the Updates page; leaving it null keeps the previous silent behaviour.
     *
     * @param  list<string>  $protected  relative paths never to overwrite
     * @param  (\Closure(array{stage:string,message:string,percent:int}):void)|null  $progress
     * @return array{from:string,to:string,copied:int,skipped:int,migrate:int,clear:int}
     */
    public function apply(string $zipPath, string $targetRoot, array $protected, string $currentVersion, bool $runCommands = true, ?\Closure $progress = null): array
    {
        $report = function (string $stage, string $message, int $percent, array $extra = []) use ($progress): void {
            $progress?->__invoke(['stage' => $stage, 'message' => $message, 'percent' => $percent] + $extra);
        };

        $report('manifest', 'Lendo o manifesto do pacote…', 22);

        $manifest = $this->readManifest($zipPath);

        if (($manifest['name'] ?? null) !== 'sgcore') {
            throw new RuntimeException('Pacote inválido: manifest sem name=sgcore.');
        }

        $newVersion = ltrim((string) ($manifest['version'] ?? ''), 'vV');
        $phpMin = (string) ($manifest['php_min'] ?? '8.3');

        $report('verify', 'Conferindo requisitos (PHP '.$phpMin.'+)…', 26);

        if ($phpMin !== '' && version_compare(PHP_VERSION, $phpMin, '<')) {
            throw new RuntimeException("Este pacote exige PHP {$phpMin}+. O servidor tem ".PHP_VERSION.'.');
        }

        $report('extract', 'Extraindo o pacote em área temporária…', 30);

        $extractTo = $this->extract($zipPath);

        try {
            if (! $this->looksLikeApp($extractTo)) {
                throw new RuntimeException('Pacote incompleto: faltam artisan/vendor.');
            }

            // Read the live .htaccess files before the copy overwrites them.
            $htaccess = $this->readHtaccesses($targetRoot);
            $total = $this->countFiles($zipPath);

            $report('files', 'Copiando arquivos…', 34, ['total' => $total]);

            $copy = $this->copyTree($extractTo, $targetRoot, $protected, $total, $report);

            $report('htaccess', 'Preservando o .htaccess e o PHP do cPanel…', 84);

            $this->mergeHtaccesses($targetRoot, $htaccess);

            $migrate = 0;
            $clear = 0;

            if ($runCommands) {
                $report('migrate', 'Rodando migrations pendentes…', 88);

                $migrate = ($this->runner())('migrate --force');

                if ($migrate !== 0) {
                    throw new RuntimeException('As migrations falharam após copiar os arquivos (exit '.$migrate.').');
                }

                $report('cache', 'Limpando caches da aplicação…', 95);

                $clear = ($this->runner())('optimize:clear');
            }

            $report('done', 'Arquivos e banco atualizados.', 100);

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

    /** Files (directories excluded) the package will write. */
    private function countFiles(string $zipPath): int
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            return 0;
        }

        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (! str_ends_with($name, '/')) {
                $total++;
            }
        }

        $zip->close();

        return $total;
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

    private function looksLikeApp(string $dir): bool
    {
        return is_file($dir.'/artisan')
            && is_file($dir.'/composer.json')
            && is_dir($dir.'/vendor');
    }

    /**
     * @param  list<string>  $protected
     * @param  (\Closure(array{stage:string,message:string,percent:int}):void)  $report
     * @return array{copied:int,skipped:int}
     */
    private function copyTree(string $src, string $dest, array $protected, int $total, \Closure $report): array
    {
        $copied = 0;
        $skipped = 0;
        $lastPercent = -1;
        $lastTick = 0;

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

            // 34% -> 82% while copying; report on percent change or every 200
            // files so the stream stays light (6.800 files per package).
            $percent = $total > 0 ? 34 + (int) floor(48 * $copied / $total) : 34;

            if ($percent !== $lastPercent || (time() - $lastTick) >= 1) {
                $lastPercent = $percent;
                $lastTick = time();

                $report('files', "Copiando arquivos… {$copied}".($total > 0 ? "/{$total}" : ''), $percent, [
                    'copied' => $copied,
                    'total' => $total,
                ]);
            }
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    /**
     * Live contents of the .htaccess files, keyed by relative path.
     *
     * @return array<string,string>
     */
    private function readHtaccesses(string $root): array
    {
        $files = [];

        foreach (self::HTACCESS_FILES as $relative) {
            $path = $root.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (is_file($path)) {
                $files[$relative] = (string) file_get_contents($path);
            }
        }

        return $files;
    }

    /**
     * Replace only the sgcore block of each .htaccess: cPanel's handler and any
     * custom directive outside the markers stay byte for byte.
     *
     * @param  array<string,string>  $existing
     */
    private function mergeHtaccesses(string $root, array $existing): void
    {
        foreach ($existing as $relative => $before) {
            $path = $root.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (! is_file($path)) {
                continue;
            }

            $incoming = (string) file_get_contents($path);
            $merged = HtaccessRules::merge($before, $incoming);

            if ($merged !== $incoming) {
                file_put_contents($path, $merged);
            }
        }
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
