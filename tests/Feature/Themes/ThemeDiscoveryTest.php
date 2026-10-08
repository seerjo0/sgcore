<?php

namespace Tests\Feature\Themes;

use App\Modules\Core\Services\Settings;
use App\Modules\Themes\Services\ThemeDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemeDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();

        $this->storagePath = sys_get_temp_dir().'/sgcore-themes-'.uniqid('', true);
        File::makeDirectory($this->storagePath, 0755, true);

        config(['cms.themes.storage_path' => $this->storagePath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storagePath);

        parent::tearDown();
    }

    private function discovery(): ThemeDiscovery
    {
        return app(ThemeDiscovery::class);
    }

    private function writeTheme(string $slug, string $manifest, array $files = []): string
    {
        $path = $this->storagePath.'/'.$slug;
        File::makeDirectory($path, 0755, true);
        file_put_contents($path.'/theme.json', $manifest);

        foreach ($files as $name => $content) {
            file_put_contents($path.'/'.$name, $content);
        }

        return $path;
    }

    private function validManifest(): string
    {
        return json_encode([
            'name' => 'Tema de Teste',
            'version' => '1.0.0',
            'templates' => ['home' => 'index.html', 'page' => 'page.html'],
            'slots' => ['site.name' => ['type' => 'text', 'scope' => 'global']],
            'menus' => ['primary' => 'Principal'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function test_builtin_themes_are_discovered_and_valid(): void
    {
        $themes = $this->discovery()->all();

        foreach (['classic', 'modern', 'gallery'] as $slug) {
            $this->assertArrayHasKey($slug, $themes);
            $this->assertTrue($themes[$slug]->builtin);
            $this->assertTrue($themes[$slug]->valid, $themes[$slug]->error ?? '');
            $this->assertNull($themes[$slug]->error);
        }

        $this->assertSame(['classic', 'gallery', 'modern'], $this->discovery()->activatable()->keys()->all());
    }

    public function test_theme_without_manifest_json_is_invalid(): void
    {
        File::makeDirectory($this->storagePath.'/quebrado', 0755, true);

        $theme = $this->discovery()->find('quebrado');

        $this->assertNotNull($theme);
        $this->assertFalse($theme->valid);
        $this->assertSame('Arquivo theme.json não encontrado.', $theme->error);
        $this->assertFalse($this->discovery()->activatable()->has('quebrado'));
    }

    public function test_manifest_with_missing_template_file_is_invalid(): void
    {
        $manifest = json_encode([
            'name' => 'Incompleto',
            'templates' => ['home' => 'index.html', 'page' => 'page.html'],
        ], JSON_UNESCAPED_SLASHES);

        $this->writeTheme('incompleto', $manifest, ['index.html' => '<html></html>']);

        $theme = $this->discovery()->find('incompleto');

        $this->assertFalse($theme->valid);
        $this->assertStringContainsString('page.html', (string) $theme->error);
    }

    public function test_manifest_with_unknown_slot_type_is_invalid(): void
    {
        $manifest = json_encode([
            'name' => 'Slot Errado',
            'templates' => ['home' => 'index.html', 'page' => 'page.html'],
            'slots' => ['qualquer' => ['type' => 'video', 'scope' => 'global']],
        ], JSON_UNESCAPED_SLASHES);

        $this->writeTheme('slot-errado', $manifest, ['index.html' => '', 'page.html' => '']);

        $theme = $this->discovery()->find('slot-errado');

        $this->assertFalse($theme->valid);
        $this->assertStringContainsString('tipo inválido', (string) $theme->error);
    }

    public function test_valid_imported_theme_is_activatable(): void
    {
        $this->writeTheme('importado', $this->validManifest(), [
            'index.html' => '<html></html>',
            'page.html' => '<html></html>',
        ]);

        $theme = $this->discovery()->find('importado');

        $this->assertTrue($theme->valid);
        $this->assertFalse($theme->builtin);
        $this->assertSame('Tema de Teste', $theme->name());
        $this->assertSame(['home' => 'index.html', 'page' => 'page.html'], $theme->templates());
        $this->assertTrue($this->discovery()->activatable()->has('importado'));
    }

    public function test_active_theme_defaults_to_config_and_ignores_invalid_settings(): void
    {
        $this->assertSame('classic', $this->discovery()->active()?->slug);

        app(Settings::class)->set('active_theme', 'modern', 'general');
        $this->assertSame('modern', $this->discovery()->active()?->slug);

        app(Settings::class)->set('active_theme', 'inexistente', 'general');
        $this->assertSame('classic', $this->discovery()->active()?->slug);
    }
}
