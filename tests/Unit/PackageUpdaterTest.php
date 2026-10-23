<?php

namespace Tests\Unit;

use App\Modules\Core\Services\HtaccessRules;
use App\Modules\Core\Services\PackageUpdater;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

class PackageUpdaterTest extends TestCase
{
    private string $appRoot;

    private string $zip;

    /** @var list<string> */
    private array $commands = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->appRoot = sys_get_temp_dir().'/sgcore-app-'.bin2hex(random_bytes(4));
        $this->zip = sys_get_temp_dir().'/sgcore-app-pkg-'.bin2hex(random_bytes(4)).'.zip';
        $this->commands = [];

        mkdir($this->appRoot.'/storage/app', 0777, true);
        mkdir($this->appRoot.'/app/Http', 0777, true);
        mkdir($this->appRoot.'/bootstrap/cache', 0777, true);
        file_put_contents($this->appRoot.'/.env', "APP_KEY=secret-local\n");
        file_put_contents($this->appRoot.'/storage/app/private.txt', 'user-data');
        file_put_contents($this->appRoot.'/bootstrap/cache/config.php', '<?php return [];');
        file_put_contents($this->appRoot.'/app/Http/Kernel.php', "OLD\n");
        file_put_contents($this->appRoot.'/artisan', "<?php\n");
        file_put_contents($this->appRoot.'/composer.json', '{}');
        mkdir($this->appRoot.'/vendor', 0777, true);
        file_put_contents($this->appRoot.'/vendor/autoload.php', "<?php\n");

        $this->makePackage('1.9.0', '8.3');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->appRoot);

        if (is_file($this->zip)) {
            @unlink($this->zip);
        }

        parent::tearDown();
    }

    public function test_apply_copies_files_and_protects_runtime(): void
    {
        $result = $this->updater()->apply(
            $this->zip,
            $this->appRoot,
            ['.env', 'storage', 'bootstrap/cache'],
            '1.0.0',
        );

        $this->assertSame('1.0.0', $result['from']);
        $this->assertSame('1.9.0', $result['to']);
        $this->assertGreaterThan(0, $result['copied']);

        // Updated app file.
        $this->assertStringContainsString('NEW', (string) file_get_contents($this->appRoot.'/app/Http/Kernel.php'));

        // Protected runtime is intact.
        $this->assertStringContainsString('secret-local', (string) file_get_contents($this->appRoot.'/.env'));
        $this->assertSame('user-data', file_get_contents($this->appRoot.'/storage/app/private.txt'));
        $this->assertFileExists($this->appRoot.'/bootstrap/cache/config.php');

        // migrate + optimize ran, in order.
        $this->assertSame(['migrate --force', 'optimize:clear'], $this->commands);
    }

    public function test_apply_keeps_the_cpanel_php_handler(): void
    {
        $block = implode("\n", [
            '# php -- BEGIN cPanel-generated handler, do not edit',
            '# Set the "ea-php85" package as the default "PHP" programming language.',
            '<IfModule mime_module>',
            '  AddHandler application/x-httpd-ea-php85___lsphp .php .php8 .phtml',
            '</IfModule>',
            '# php -- END cPanel-generated handler, do not edit',
        ]);

        file_put_contents($this->appRoot.'/.htaccess', "# regras antigas\n".$block."\n");

        $this->updater()->apply($this->zip, $this->appRoot, ['.env', 'storage', 'bootstrap/cache'], '1.0.0');

        $htaccess = (string) file_get_contents($this->appRoot.'/.htaccess');

        $this->assertStringContainsString('sgcore package rules', $htaccess);
        $this->assertStringContainsString('AddHandler application/x-httpd-ea-php85___lsphp', $htaccess);
        $this->assertSame(2, substr_count($htaccess, 'cPanel-generated handler'));
    }

    public function test_apply_keeps_a_bare_php_handler_line(): void
    {
        mkdir($this->appRoot.'/public', 0777, true);
        file_put_contents(
            $this->appRoot.'/public/.htaccess',
            "AddHandler application/x-httpd-alt-php85___lsphp .php\nRewriteEngine On\n"
        );

        $this->updater()->apply($this->zip, $this->appRoot, ['.env', 'storage', 'bootstrap/cache'], '1.0.0');

        $htaccess = (string) file_get_contents($this->appRoot.'/public/.htaccess');

        $this->assertStringContainsString('sgcore public rules', $htaccess);
        $this->assertStringContainsString('AddHandler application/x-httpd-alt-php85___lsphp .php', $htaccess);
        $this->assertSame(1, substr_count($htaccess, 'AddHandler'));
    }

    public function test_keeps_everything_outside_the_marked_block(): void
    {
        $existing = HtaccessRules::BEGIN."\n# rules from 1.2.0\nRewriteRule ^ old [L]\n".HtaccessRules::END."\n\n"
            ."# php -- BEGIN cPanel-generated handler, do not edit\n"
            ."AddHandler application/x-httpd-ea-php85___lsphp .php\n"
            ."# php -- END cPanel-generated handler, do not edit\n\n"
            ."# minha regra\nRedirect 301 /velho /novo\n";

        file_put_contents($this->appRoot.'/.htaccess', $existing);

        $this->updater()->apply($this->zip, $this->appRoot, ['.env', 'storage', 'bootstrap/cache'], '1.0.0');

        $htaccess = (string) file_get_contents($this->appRoot.'/.htaccess');

        $this->assertStringNotContainsString('RewriteRule ^ old [L]', $htaccess);
        $this->assertStringContainsString('RewriteRule ^ index.php [L]', $htaccess);
        $this->assertStringContainsString('AddHandler application/x-httpd-ea-php85___lsphp .php', $htaccess);
        $this->assertStringContainsString('# minha regra'."\n".'Redirect 301 /velho /novo', $htaccess);
        $this->assertSame(1, substr_count($htaccess, HtaccessRules::BEGIN));
    }

    public function test_reports_progress_while_copying(): void
    {
        $events = [];

        $this->updater()->apply(
            $this->zip,
            $this->appRoot,
            ['.env', 'storage', 'bootstrap/cache'],
            '1.0.0',
            true,
            function (array $event) use (&$events): void {
                $events[] = $event;
            },
        );

        $stages = array_column($events, 'stage');
        $percents = array_column($events, 'percent');

        $this->assertSame('manifest', $stages[0]);
        $this->assertContains('files', $stages);
        $this->assertContains('migrate', $stages);
        $this->assertSame('done', $stages[array_key_last($stages)]);
        $this->assertSame(100, $percents[array_key_last($percents)]);

        $sorted = $percents;
        sort($sorted);
        $this->assertSame($sorted, $percents);
    }

    public function test_rejects_wrong_package_name(): void
    {
        $this->makePackage('1.9.0', '8.3', 'wordpress');

        $this->expectException(RuntimeException::class);
        $this->updater()->apply($this->zip, $this->appRoot, ['.env'], '1.0.0');
    }

    public function test_rejects_too_new_php_requirement(): void
    {
        $this->makePackage('1.9.0', '99.0');

        $this->expectException(RuntimeException::class);
        $this->updater()->apply($this->zip, $this->appRoot, ['.env'], '1.0.0');
    }

    private function updater(): PackageUpdater
    {
        return new PackageUpdater(function (string $command): int {
            $this->commands[] = $command;

            return 0;
        });
    }

    private function makePackage(string $version, string $phpMin, string $name = 'sgcore'): void
    {
        if (is_file($this->zip)) {
            @unlink($this->zip);
        }

        $zip = new ZipArchive;
        $zip->open($this->zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('sgcore-package.json', json_encode([
            'name' => $name, 'version' => $version, 'php_min' => $phpMin, 'protected' => ['.env'],
        ]));
        $zip->addFromString('app/Http/Kernel.php', "NEW\n");
        $zip->addFromString('artisan', "<?php // new\n");
        $zip->addFromString('composer.json', '{"name":"seerjo0/sgcore"}');
        $zip->addFromString('vendor/autoload.php', "<?php\n");
        $zip->addFromString('bootstrap/cache/should-not-land.php', "<?php\n");
        $zip->addFromString('.htaccess', HtaccessRules::wrap("# sgcore package rules\nRewriteRule ^ index.php [L]"));
        $zip->addFromString('public/.htaccess', HtaccessRules::wrap("# sgcore public rules\nRewriteRule ^ index.php [L]"));

        $zip->close();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $dir.DIRECTORY_SEPARATOR.$entry;
            is_dir($full) && ! is_link($full) ? $this->rrmdir($full) : @unlink($full);
        }

        @rmdir($dir);
    }
}
