<?php

namespace Tests\Feature;

use App\Modules\Core\Services\EnvironmentWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnvironmentWriterTest extends TestCase
{
    use RefreshDatabase;

    private string $envFile;

    private string $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envFile = sys_get_temp_dir().'/sgcore-env-'.uniqid('', true).'.env';
        $this->template = sys_get_temp_dir().'/sgcore-tpl-'.uniqid('', true).'.example';

        file_put_contents($this->template, <<<'ENV'
APP_NAME=placeholder
APP_ENV=local
APP_KEY=
APP_DEBUG=true
DB_CONNECTION=sqlite
CUSTOM_KEEP=yes
ENV);

        config([
            'cms.installer.env_file' => $this->envFile,
            'cms.installer.env_template' => $this->template,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->envFile, $this->template] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }

        parent::tearDown();
    }

    public function test_writer_overrides_app_env_and_debug_when_provided(): void
    {
        app(EnvironmentWriter::class)->write([
            'APP_NAME' => 'sgcore',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'sgcore',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
        ]);

        $contents = file_get_contents($this->envFile);

        $this->assertStringContainsString('APP_ENV=production', $contents);
        $this->assertStringContainsString('APP_DEBUG=false', $contents);
        // Non-managed keys survive untouched.
        $this->assertStringContainsString('CUSTOM_KEEP=yes', $contents);
    }

    public function test_writer_preserves_existing_app_env_when_not_provided(): void
    {
        // Pre-populate .env from template.
        copy($this->template, $this->envFile);

        app(EnvironmentWriter::class)->write([
            'APP_NAME' => 'sgcore',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'sgcore',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
        ]);

        $contents = file_get_contents($this->envFile);
        $this->assertStringContainsString('APP_ENV=local', $contents);
        $this->assertStringContainsString('APP_DEBUG=true', $contents);
    }
}
