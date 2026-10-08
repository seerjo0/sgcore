<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Enums\Role;
use App\Modules\Core\Services\InstallerLock;
use App\Modules\Core\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PDO;
use Tests\TestCase;

class CliInstallTest extends TestCase
{
    use RefreshDatabase;

    private string $lockFile;

    private string $envFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockFile = sys_get_temp_dir().'/sgcore-lock-'.uniqid('', true);
        $this->envFile = sys_get_temp_dir().'/sgcore-env-'.uniqid('', true).'.env';

        config([
            'cms.installer.lock_file' => $this->lockFile,
            'cms.installer.env_file' => $this->envFile,
            'cms.installer.create_storage_link' => false,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->lockFile)) {
            unlink($this->lockFile);
        }

        if (is_file($this->envFile)) {
            unlink($this->envFile);
        }

        $this->dropTestDatabase();

        parent::tearDown();
    }

    public function test_install_command_performs_a_full_headless_installation(): void
    {
        if (! $this->mysqlServerAvailable()) {
            $this->markTestSkipped('Servidor MySQL/MariaDB indisponível para o teste de CLI.');
        }

        $exitCode = Artisan::call('cms:install', [
            '--db-host' => '127.0.0.1',
            '--db-port' => '3306',
            '--db-database' => 'sgcore_cli_test',
            '--db-username' => 'root',
            '--db-password' => 'root',
            '--site-name' => 'Site via CLI',
            '--admin-name' => 'Admin CLI',
            '--admin-email' => 'cli@exemplo.com',
            '--admin-password' => 'senha-forte-123',
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $env = (string) file_get_contents($this->envFile);
        $this->assertStringContainsString('DB_DATABASE=sgcore_cli_test', $env);
        $this->assertStringContainsString('APP_KEY=base64:', $env);

        $this->assertTrue($this->app->make(InstallerLock::class)->exists());

        $admin = User::query()->where('email', 'cli@exemplo.com')->firstOrFail();
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertSame('Admin CLI', $admin->name);
        $this->assertSame(
            'Site via CLI',
            (string) $this->app->make(Settings::class)->get('site_name'),
        );
    }

    public function test_install_command_refuses_when_already_installed(): void
    {
        $this->app->make(InstallerLock::class)->create();

        $exitCode = Artisan::call('cms:install');

        $this->assertSame(1, $exitCode);
    }

    private function mysqlServerAvailable(): bool
    {
        try {
            new PDO('mysql:host=127.0.0.1;port=3306', 'root', 'root', [
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function dropTestDatabase(): void
    {
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', 'root', [
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('DROP DATABASE IF EXISTS sgcore_cli_test');
        } catch (\Throwable) {
            // Servidor indisponível ou banco já removido.
        }
    }
}
