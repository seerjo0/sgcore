<?php

namespace Tests\Unit;

use App\Modules\Core\Services\PackageBuilder;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class PackageBuilderTest extends TestCase
{
    private string $staging;

    private string $zip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staging = sys_get_temp_dir().'/sgcore-pkg-src-'.bin2hex(random_bytes(4));
        $this->zip = sys_get_temp_dir().'/sgcore-pkg-out-'.bin2hex(random_bytes(4)).'.zip';

        mkdir($this->staging.'/app', 0777, true);
        mkdir($this->staging.'/public/build', 0777, true);
        mkdir($this->staging.'/vendor', 0777, true);
        mkdir($this->staging.'/bootstrap/cache', 0777, true);
        mkdir($this->staging.'/storage/app', 0777, true);
        mkdir($this->staging.'/tests/Feature', 0777, true);
        mkdir($this->staging.'/.github/workflows', 0777, true);

        file_put_contents($this->staging.'/artisan', "<?php\n");
        file_put_contents($this->staging.'/composer.json', '{}');
        file_put_contents($this->staging.'/composer.lock', '{}');
        file_put_contents($this->staging.'/phpunit.xml', '<phpunit/>');
        file_put_contents($this->staging.'/.env', "APP_SECRET=do-not-ship\n");
        file_put_contents($this->staging.'/.env.example', "APP_NAME=\n");
        file_put_contents($this->staging.'/vendor/autoload.php', "<?php\n");
        file_put_contents($this->staging.'/public/index.php', "<?php\n");
        file_put_contents($this->staging.'/public/build/manifest.json', '{}');
        file_put_contents($this->staging.'/app/Kernel.php', "<?php\n");
        file_put_contents($this->staging.'/tests/Feature/FooTest.php', "<?php\n");
        file_put_contents($this->staging.'/.github/workflows/ci.yml', "on: push\n");
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->staging);

        if (is_file($this->zip)) {
            @unlink($this->zip);
        }

        parent::tearDown();
    }

    public function test_assemble_creates_zip_with_expected_entries(): void
    {
        $stats = (new PackageBuilder)->assemble($this->staging, $this->zip, [
            'name' => 'sgcore',
            'version' => '1.2.3',
            'php_min' => '8.3',
        ]);

        $this->assertGreaterThan(0, $stats['files']);
        $this->assertFileExists($stats['path']);

        $entries = $this->zipEntries($this->zip);

        // Shipped
        $this->assertContains('index.php', $entries);
        $this->assertContains('.htaccess', $entries);
        $this->assertContains('web.config', $entries);
        $this->assertContains('sgcore-package.json', $entries);
        $this->assertContains('app/Kernel.php', $entries);
        $this->assertContains('vendor/autoload.php', $entries);
        $this->assertContains('public/build/manifest.json', $entries);
        $this->assertContains('.env.example', $entries);

        // Pruned / never shipped
        $this->assertNotContains('.env', $entries);
        $this->assertNotContains('phpunit.xml', $entries);
        $this->assertNotContains('tests/Feature/FooTest.php', $entries);
        $this->assertNotContains('.github/workflows/ci.yml', $entries);

        foreach ($entries as $entry) {
            $this->assertStringNotContainsString('tests/', $entry);
            $this->assertStringNotContainsString('/.git', $entry);
        }
    }

    public function test_manifest_has_version_and_protected_paths(): void
    {
        (new PackageBuilder)->assemble($this->staging, $this->zip, [
            'name' => 'sgcore',
            'version' => 'v1.2.3',
            'php_min' => '8.3',
        ]);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->zip) === true);
        $raw = $zip->getFromName('sgcore-package.json');
        $zip->close();

        $manifest = json_decode((string) $raw, true);

        $this->assertIsArray($manifest);
        $this->assertSame('sgcore', $manifest['name']);
        $this->assertSame('1.2.3', $manifest['version']);
        $this->assertSame('8.3', $manifest['php_min']);
        $this->assertContains('.env', $manifest['protected']);
        $this->assertContains('storage', $manifest['protected']);
    }

    public function test_root_entry_boots_from_project_root(): void
    {
        (new PackageBuilder)->assemble($this->staging, $this->zip, [
            'name' => 'sgcore', 'version' => '1.0.0', 'php_min' => '8.3',
        ]);

        $zip = new ZipArchive;
        $zip->open($this->zip);
        $index = (string) $zip->getFromName('index.php');
        $htaccess = (string) $zip->getFromName('.htaccess');
        $webconfig = (string) $zip->getFromName('web.config');
        $zip->close();

        // Front controller resolves vendor/bootstrap from the project root (no ../).
        $this->assertStringContainsString("__DIR__.'/vendor/autoload.php'", $index);
        $this->assertStringContainsString("__DIR__.'/bootstrap/app.php'", $index);
        $this->assertStringContainsString('usePublicPaths', $index);

        // Apache rules deny internal dirs.
        $this->assertStringContainsString('vendor', $htaccess);
        $this->assertStringContainsString('storage', $htaccess);

        // IIS rules deny dotfiles/.env.
        $this->assertStringContainsString('.env', $webconfig);
        $this->assertStringContainsString('hiddenSegments', $webconfig);
    }

    /**
     * @return list<string>
     */
    private function zipEntries(string $zip): array
    {
        $archive = new ZipArchive;
        $this->assertSame(true, $archive->open($zip) === true);
        $entries = [];

        for ($i = 0; $i < $archive->numFiles; $i++) {
            $entries[] = (string) $archive->getNameIndex($i);
        }

        $archive->close();

        return $entries;
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
