<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Enums\Role;
use App\Modules\Core\Exceptions\DatabaseSetupException;
use App\Modules\Core\Services\DatabaseSetup;
use App\Modules\Core\Services\InstallerLock;
use App\Modules\Core\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallerFlowTest extends TestCase
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

        parent::tearDown();
    }

    public function test_requirements_page_lists_checks_and_creates_env_file(): void
    {
        $response = $this->get('/instalar');

        $response->assertOk();
        $response->assertSee('Requisitos');
        $response->assertSee('pdo_mysql');
        $this->assertFileExists($this->envFile);
    }

    public function test_uninstalled_site_redirects_to_installer(): void
    {
        $this->get('/')->assertRedirect('/instalar');
        $this->get('/qualquer-pagina')->assertRedirect('/instalar');
        $this->get('/admin')->assertRedirect('/instalar');
    }

    public function test_database_step_tests_connection_and_writes_env(): void
    {
        $this->mock(DatabaseSetup::class, function ($mock): void {
            $mock->shouldReceive('testConnection')
                ->once()
                ->with('127.0.0.1', 3306, 'sgcore_flow', 'root', 'root');
        });

        $response = $this->post('/instalar/banco', [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'sgcore_flow',
            'username' => 'root',
            'password' => 'root',
        ]);

        $response->assertRedirect(route('installer.site'));
        $this->assertFileExists($this->envFile);

        $contents = (string) file_get_contents($this->envFile);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $contents);
        $this->assertStringContainsString('DB_DATABASE=sgcore_flow', $contents);
        $this->assertStringContainsString('DB_PASSWORD=root', $contents);
        $this->assertStringContainsString('APP_KEY=base64:', $contents);
        $this->assertStringContainsString('LOG_LEVEL=debug', $contents);
    }

    public function test_database_step_rejects_malicious_values_without_writing_env(): void
    {
        $this->mock(DatabaseSetup::class, function ($mock): void {
            $mock->shouldReceive('testConnection')->never();
        });

        $response = $this->post('/instalar/banco', [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => "invalido\nevil=1",
            'username' => 'root',
            'password' => '',
        ]);

        $response->assertOk();
        $response->assertViewHas('errors');
        $this->assertFileDoesNotExist($this->envFile);
    }

    public function test_database_step_shows_connection_failure(): void
    {
        $this->mock(DatabaseSetup::class, function ($mock): void {
            $mock->shouldReceive('testConnection')
                ->once()
                ->andThrow(new DatabaseSetupException('credenciais inválidas'));
        });

        $response = $this->post('/instalar/banco', [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'sgcore',
            'username' => 'root',
            'password' => 'errada',
        ]);

        $response->assertOk();
        $response->assertSee('credenciais inválidas');
        $this->assertFileDoesNotExist($this->envFile);
    }

    public function test_site_step_validates_required_fields(): void
    {
        $response = $this->post('/instalar/site', [
            'site_name' => '',
            'admin_name' => '',
            'admin_email' => 'nao-e-email',
            'admin_password' => 'curta',
        ]);

        $response->assertOk();
        $response->assertViewHas('errors');
        $this->assertFalse(File::exists($this->lockFile));
    }

    public function test_full_installation_creates_admin_settings_and_lock(): void
    {
        $response = $this->post('/instalar/site', [
            'site_name' => 'Site Teste',
            'admin_name' => 'João Admin',
            'admin_email' => 'admin@exemplo.com',
            'admin_password' => 'segredo123',
            'admin_password_confirmation' => 'segredo123',
        ]);

        $response->assertOk();
        $response->assertSee('Site Teste instalado');
        $response->assertDontSee('Falha na instalação');

        $this->assertTrue($this->app->make(InstallerLock::class)->exists());

        $admin = User::query()->where('email', 'admin@exemplo.com')->firstOrFail();
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue($admin->active);
        $this->assertTrue(Hash::check('segredo123', $admin->password));

        $this->assertSame('Site Teste', $this->app->make(Settings::class)->get('site_name'));
    }

    public function test_installer_routes_are_blocked_once_installed(): void
    {
        $this->app->make(InstallerLock::class)->create();

        $this->get('/instalar')->assertRedirect('/');
        $this->post('/instalar/site')->assertRedirect('/');
        $this->get('/instalar/concluido')->assertOk();
        $this->get('/')->assertOk();
    }
}
