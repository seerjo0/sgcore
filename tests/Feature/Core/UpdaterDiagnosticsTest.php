<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Services\HtaccessRules;
use App\Modules\Core\Services\PackageUpdater;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class UpdaterDiagnosticsTest extends TestCase
{
    private string $appRoot;

    private string $zip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appRoot = sys_get_temp_dir().'/sgcore-diag-app-'.bin2hex(random_bytes(4));
        $this->zip = sys_get_temp_dir().'/sgcore-diag-pkg-'.bin2hex(random_bytes(4)).'.zip';

        mkdir($this->appRoot.'/vendor', 0777, true);
        file_put_contents($this->appRoot.'/artisan', "<?php\n");
        file_put_contents($this->appRoot.'/vendor/autoload.php', "<?php\n");
        file_put_contents($this->appRoot.'/composer.json', '{}');

        $zip = new ZipArchive;
        $zip->open($this->zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('sgcore-package.json', json_encode([
            'name' => 'sgcore', 'version' => '9.9.9', 'php_min' => '8.3',
        ]));
        $zip->addFromString('artisan', "<?php // new\n");
        $zip->addFromString('composer.json', '{"name":"seerjo0/sgcore"}');
        $zip->addFromString('vendor/autoload.php', "<?php\n");
        $zip->addFromString('.htaccess', HtaccessRules::wrap("# sgcore package rules\n"));
        $zip->close();
    }

    protected function tearDown(): void
    {
        @unlink($this->zip);

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->appRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->appRoot);

        parent::tearDown();
    }

    public function test_migration_failure_surfaces_the_real_artisan_output(): void
    {
        // The old code threw "exit 1" and threw the console output away; the
        // operator on shared hosting was left with no cause at all.
        Artisan::shouldReceive('call')->once()->andReturn(1);
        Artisan::shouldReceive('output')->andReturn(
            "  SQLSTATE[42000]: Syntax error or access violation: 1142 ALTER command denied to user 'u123'@'localhost' for table 'pages'"
        );

        $updater = new PackageUpdater;

        try {
            $updater->apply($this->zip, $this->appRoot, ['.env'], '1.2.3');
            $this->fail('esperava RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('As migrations falharam', $e->getMessage());
            $this->assertStringContainsString('Causa:', $e->getMessage());
            $this->assertStringContainsString("1142 ALTER command denied to user 'u123'", $e->getMessage());
        }
    }

    public function test_thrown_artisan_exceptions_also_reach_the_failure_message(): void
    {
        Artisan::shouldReceive('call')->once()->andThrow(new RuntimeException('Class "App\\Modules\\Core\\X" not found'));

        $updater = new PackageUpdater;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Class "App\\Modules\\Core\\X" not found');

        $updater->apply($this->zip, $this->appRoot, ['.env'], '1.2.3');
    }

    public function test_the_default_runner_passes_force_as_a_double_dash_option(): void
    {
        // Regression: parseArgs stripped the "--", so Artisan::call received
        // ['force' => true] — ArrayInput reads a key without dashes as an
        // ARGUMENT, migrate has none, and EVERY panel package update died with
        // "The force argument does not exist" -> the runner's catch turned it
        // into a blind "exit 1" and rolled back. The real call was never
        // covered by tests (they all inject a fake runner).
        Artisan::shouldReceive('call')
            ->with('migrate', ['--force' => true])
            ->once()
            ->ordered()
            ->andReturn(0);
        Artisan::shouldReceive('call')
            ->with('optimize:clear', [])
            ->once()
            ->ordered()
            ->andReturn(0);

        $result = (new PackageUpdater)->apply($this->zip, $this->appRoot, ['.env'], '1.2.3');

        $this->assertSame(0, $result['migrate']);
        $this->assertSame(0, $result['clear']);
    }

    public function test_successful_flow_does_not_mention_any_cause(): void
    {
        Artisan::shouldReceive('call')->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('Nothing to migrate');

        $result = (new PackageUpdater)->apply($this->zip, $this->appRoot, ['.env'], '1.2.3');

        $this->assertSame(['migrate' => 0, 'clear' => 0], array_intersect_key($result, array_flip(['migrate', 'clear'])));
    }
}
