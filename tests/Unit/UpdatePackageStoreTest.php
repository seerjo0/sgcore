<?php

namespace Tests\Unit;

use App\Modules\Core\Services\UpdatePackageStore;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UpdatePackageStoreTest extends TestCase
{
    private UpdatePackageStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new UpdatePackageStore;

        File::deleteDirectory($this->store->dir());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->store->dir());

        parent::tearDown();
    }

    public function test_there_is_nothing_stored_by_default(): void
    {
        $this->assertNull($this->store->current());
        $this->assertNull($this->store->path());
        $this->assertFalse($this->store->discard());
    }

    public function test_it_stores_the_package_with_its_metadata(): void
    {
        $stored = $this->store->store($this->fakeZip('1.1.0-r2'), '1.1.0-r2', 'sgcore-1.1.0-r2-public_html.zip');

        $this->assertSame('1.1.0-r2', $stored['version']);
        $this->assertSame('sgcore-1.1.0-r2-public_html.zip', $stored['name']);
        $this->assertSame(9, $stored['bytes']);
        $this->assertSame(hash('sha256', 'zip-bytes'), $stored['sha256']);
        $this->assertFileExists($stored['path']);

        $current = $this->store->current();

        $this->assertNotNull($current);
        $this->assertSame($stored['path'], $current['path']);
    }

    public function test_storing_replaces_the_previous_package(): void
    {
        $this->store->store($this->fakeZip('1.1.0-r1'), '1.1.0-r1');
        $this->store->store($this->fakeZip('1.1.0-r2'), '1.1.0-r2');

        $this->assertSame('1.1.0-r2', $this->store->current()['version']);
        $this->assertCount(1, glob($this->store->dir().'/*.zip'));
    }

    public function test_a_vanished_zip_is_ignored_and_the_metadata_dropped(): void
    {
        $stored = $this->store->store($this->fakeZip('1.1.0-r2'), '1.1.0-r2');
        File::delete($stored['path']);

        $this->assertNull($this->store->current());
        $this->assertSame([], glob($this->store->dir().'/*.json'));
    }

    public function test_discard_removes_zip_and_metadata(): void
    {
        $this->store->store($this->fakeZip('1.1.0-r2'), '1.1.0-r2');

        $this->assertTrue($this->store->discard());
        $this->assertNull($this->store->current());
    }

    public function test_the_version_is_sanitized_for_the_file_name(): void
    {
        $stored = $this->store->store($this->fakeZip('x'), '../../etc/passwd');

        $this->assertSame($this->store->dir(), dirname($stored['path']));
        $this->assertStringNotContainsString('/', basename($stored['path']));
        $this->assertStringNotContainsString('\\', basename($stored['path']));
    }

    private function fakeZip(string $seed): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sgcore-test-').'.zip';
        file_put_contents($path, 'zip-bytes');

        return $path;
    }
}
