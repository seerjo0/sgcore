<?php

namespace Tests\Feature;

use App\Modules\Core\Services\InstallerLock;
use Tests\TestCase;

class InstallerLockTest extends TestCase
{
    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockFile = sys_get_temp_dir().'/sgcore-'.uniqid('', true).'.lock';
        config(['cms.installer.lock_file' => $this->lockFile]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->lockFile)) {
            unlink($this->lockFile);
        }

        parent::tearDown();
    }

    public function test_lock_does_not_exist_before_installation(): void
    {
        $lock = $this->app->make(InstallerLock::class);

        $this->assertFalse($lock->exists());
    }

    public function test_create_marks_application_as_installed_with_metadata(): void
    {
        $lock = $this->app->make(InstallerLock::class);

        $lock->create(['site_name' => 'Meu CMS']);

        $this->assertTrue($lock->exists());
        $this->assertFileExists($this->lockFile);

        $payload = json_decode((string) file_get_contents($this->lockFile), true);

        $this->assertArrayHasKey('installed_at', $payload);
        $this->assertSame('Meu CMS', $payload['site_name']);
    }

    public function test_forget_allows_a_fresh_installation(): void
    {
        $lock = $this->app->make(InstallerLock::class);

        $lock->create();
        $lock->forget();

        $this->assertFalse($lock->exists());
    }
}
