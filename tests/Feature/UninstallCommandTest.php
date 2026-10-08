<?php

namespace Tests\Feature;

use App\Modules\Core\Services\InstallerLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UninstallCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockFile = sys_get_temp_dir().'/sgcore-lock-'.uniqid('', true);
        config(['cms.installer.lock_file' => $this->lockFile]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->lockFile)) {
            unlink($this->lockFile);
        }

        parent::tearDown();
    }

    public function test_uninstall_fails_when_not_installed(): void
    {
        $exitCode = Artisan::call('cms:uninstall', ['--force' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_uninstall_drops_tables_and_removes_lock(): void
    {
        $lock = $this->app->make(InstallerLock::class);
        $lock->create();

        $exitCode = Artisan::call('cms:uninstall', ['--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertFalse($lock->exists());
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('settings'));
    }
}
