<?php

namespace App\Modules\Core\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * Builds the distributable "public_html" package (a self-contained zip with
 * vendor/ + built assets + front-controller entry + hardened rewrite rules),
 * the WordPress-style artifact attached to a GitHub Release.
 *
 * assemble() is a pure filesystem operation (no composer/git/node) so it can be
 * unit-tested against a prepared staging directory; the composer install and
 * npm build that feed it live in the sgcore:package command.
 */
class PackageBuilder
{
    /** Directories/files that must never ship in the package. */
    private const PRUNE = [
        '.git', '.github', '.ai', '.claude', '.idea', '.vscode',
        'node_modules', 'tests', 'vendor/bin', 'dist',
        'phpunit.xml', 'phpunit.xml.dist', 'pint.json',
        '.gitignore', '.gitattributes', '.editorconfig',
    ];

    /** Relative paths that must ship, dotfiles that would otherwise be pruned. */
    private const KEEP = [
        '.env.example',
    ];

    /**
     * @param  array{name:string,version:string,php_min:string}  $meta
     * @return array{files:int,bytes:int,path:string}
     */
    public function assemble(string $stagingDir, string $outputZip, array $meta): array
    {
        if (! is_dir($stagingDir)) {
            throw new RuntimeException("Diretório de staging não existe: {$stagingDir}");
        }

        $name = (string) ($meta['name'] ?? 'sgcore');
        $version = ltrim((string) ($meta['version'] ?? '0.0.0'), 'vV');
        $phpMin = (string) ($meta['php_min'] ?? '8.3');

        $this->writeRootFrontController($stagingDir);
        $this->writeRewriteRules($stagingDir);
        $this->writeManifest($stagingDir, $name, $version, $phpMin);
        $this->prune($stagingDir);

        return $this->zip($stagingDir, $outputZip);
    }

    /**
     * Root index.php that lets the whole project run directly under a web root
     * (mode b). Laravel's own public/index.php is untouched (mode a).
     */
    private function writeRootFrontController(string $dir): void
    {
        $stub = <<<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// sgcore shared-hosting entry point.
// Serve the app when the document root is the project root itself (no symlink).
// If your host allows pointing the document root at public/, prefer that for
// clean asset URLs and drop this file.

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

// When booted from the project root, Laravel still looks for built assets under
// public/. Point it there so Vite's manifest resolves without moving files.
$app->usePublicPaths(__DIR__.'/public');

$app->handleRequest(Request::capture());

PHP;

        file_put_contents($dir.'/index.php', $stub);
    }

    /**
     * Apache/LiteSpeed .htaccess + IIS web.config at the project root. They deny
     * direct access to everything that is not a real static file or the front
     * controller, so app/, vendor/, storage/, bootstrap/ and .env can't be
     * fetched over HTTP when the whole project sits in the web root.
     */
    private function writeRewriteRules(string $dir): void
    {
        $htaccess = <<<'HTTPEX'
# sgcore shared-hosting rules (Apache / LiteSpeed).
# Only index.php is executed; unknown paths are routed to it. Everything that is
# a real file under ./public or ./build is served directly. No directory listing,
# no dotfiles, no direct access to app/, vendor/, storage/, bootstrap/.

Options -Indexes -MultiViews

<IfModule mod_rewrite.c>
    RewriteEngine On

    # Never serve these regardless of extension
    RewriteRule (?:^|/)\.(env|gitignore|htaccess|htpasswd|env\.local) - [F,L,NC]
    RewriteRule ^(?:app|bootstrap/cache|config|database|node_modules|resources|routes|storage|tests|vendor)/ - [F,L,NC]

    # Built assets live under public/; expose them without the /public prefix
    RewriteCond %{DOCUMENT_ROOT}/public%{REQUEST_URI} -f
    RewriteRule ^(.*)$ public/$1 [L]

    # Route everything else through the front controller
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
</IfModule>
HTTPEX;

        file_put_contents($dir.'/.htaccess', $htaccess."\n");

        $webconfig = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<!-- sgcore shared-hosting rules (IIS). Mirrors the Apache .htaccess deny list. -->
<configuration>
  <system.webServer>
    <security>
      <requestFiltering>
        <hiddenSegments>
          <add segment="app" />
          <add segment="bootstrap" />
          <add segment="config" />
          <add segment="database" />
          <add segment="resources" />
          <add segment="routes" />
          <add segment="storage" />
          <add segment="tests" />
          <add segment="vendor" />
        </hiddenSegments>
        <fileExtensions>
          <add fileExtension=".env" allowed="false" />
          <add fileExtension="ini" allowed="false" />
        </fileExtensions>
      </requestFiltering>
    </security>
    <staticContent>
      <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="30.00:00:00" />
    </staticContent>
    <rewrite>
      <rules>
        <clear />
        <rule name="sgcore Front Controller" stopProcessing="true">
          <match url="." ignoreCase="false" />
          <conditions logicalGrouping="MatchAll">
            <add input="{REQUEST_FILENAME}" matchType="IsFile" ignoreCase="false" negate="true" />
            <add input="{REQUEST_FILENAME}" matchType="IsDirectory" ignoreCase="false" negate="true" />
          </conditions>
          <action type="Rewrite" url="index.php" />
        </rule>
      </rules>
    </rewrite>
  </system.webServer>
</configuration>
XML;

        file_put_contents($dir.'/web.config', $webconfig."\n");
    }

    private function writeManifest(string $dir, string $name, string $version, string $phpMin): void
    {
        $manifest = [
            'name' => $name,
            'version' => $version,
            'php_min' => $phpMin,
            'built_at' => date('c'),
            'entry' => 'index.php',
            'protected' => [
                '.env',
                'storage',
                'bootstrap/cache',
            ],
        ];

        file_put_contents(
            $dir.'/sgcore-package.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    private function prune(string $dir): void
    {
        $dir = rtrim($dir, '/\\');

        foreach (self::PRUNE as $rel) {
            $path = $dir.'/'.str_replace('/', DIRECTORY_SEPARATOR, $rel);

            if (is_file($path)) {
                @unlink($path);

                continue;
            }

            if (is_dir($path)) {
                $this->removeDir($path);
            }
        }

        // Runtime junk under storage/ and bootstrap/cache is never present in a
        // `git archive` staging dir (only tracked files + their .gitignore), so
        // the shipped package keeps the directory skeleton but no logs/sessions.
    }

    /**
     * @return array{files:int,bytes:int,path:string}
     */
    private function zip(string $dir, string $outputZip): array
    {
        $dir = rtrim($dir, '/\\');

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensão PHP zip é necessária para gerar o pacote.');
        }

        $outDir = dirname($outputZip);

        if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
            throw new RuntimeException("Não foi possível criar {$outDir}.");
        }

        @unlink($outputZip);

        $zip = new ZipArchive;

        if ($zip->open($outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Falha ao criar {$outputZip}.");
        }

        $files = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if (! $item->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($dir) + 1));

            if (! $this->shouldShip($relative)) {
                continue;
            }

            $zip->addFile($item->getPathname(), $relative);
            $files++;
        }

        $zip->close();

        clearstatcache(true, $outputZip);

        return [
            'files' => $files,
            'bytes' => is_file($outputZip) ? (int) filesize($outputZip) : 0,
            'path' => $outputZip,
        ];
    }

    private function shouldShip(string $relative): bool
    {
        $normalized = ltrim($relative, '/');

        foreach (self::KEEP as $keep) {
            if ($normalized === $keep) {
                return true;
            }
        }

        if (preg_match('#^\.env($|\.|/)#', $normalized) === 1 && $normalized !== '.env.example') {
            return false;
        }

        foreach (self::PRUNE as $pruned) {
            $needle = rtrim($pruned, '/');

            if ($normalized === $needle || str_starts_with($normalized, $needle.'/')) {
                return false;
            }
        }

        return true;
    }

    private function removeDir(string $path): void
    {
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
