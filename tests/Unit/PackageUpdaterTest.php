<?php

namespace Tests\Unit;

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
