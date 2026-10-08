<?php

namespace Tests\Feature\Themes;

use App\Models\User;
use App\Modules\Core\Services\Settings;
use App\Modules\Themes\Services\ThemeDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();

        $this->storagePath = sys_get_temp_dir().'/sgcore-appearance-'.uniqid('', true);
        File::makeDirectory($this->storagePath, 0755, true);

        config(['cms.themes.storage_path' => $this->storagePath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storagePath);

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.aparencia.index'))->assertRedirect(route('login'));
    }

    public function test_editor_cannot_manage_appearance(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('admin.aparencia.index'))->assertForbidden();
        $this->actingAs($editor)->post(route('admin.aparencia.ativar'), ['slug' => 'modern'])->assertForbidden();
    }

    public function test_admin_sees_all_themes_with_active_marker(): void
    {
        $admin = User::factory()->admin()->create();

        app(Settings::class)->set('active_theme', 'classic', 'general');

        $this->actingAs($admin)
            ->get(route('admin.aparencia.index'))
            ->assertOk()
            ->assertSee('Clássico')
            ->assertSee('Moderno')
            ->assertSee('Galeria')
            ->assertSee('Ativo');
    }

    public function test_admin_can_activate_a_valid_theme(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.aparencia.ativar'), ['slug' => 'modern'])
            ->assertRedirect();

        $this->assertSame('modern', app(Settings::class)->get('active_theme'));
    }

    public function test_activating_an_unknown_theme_shows_an_error(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.aparencia.index'))
            ->post(route('admin.aparencia.ativar'), ['slug' => 'inexistente'])
            ->assertRedirect(route('admin.aparencia.index'))
            ->assertSessionHasErrors('slug');

        $this->assertNotSame('inexistente', app(Settings::class)->get('active_theme'));
    }

    public function test_admin_can_import_a_theme_zip(): void
    {
        $admin = User::factory()->admin()->create();

        $zip = $this->validZip('novo-tema');

        $this->actingAs($admin)
            ->post(route('admin.aparencia.store'), ['file' => $zip])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(app(ThemeDiscovery::class)->find('novo-tema')?->valid);
    }

    public function test_import_rejects_invalid_files(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.aparencia.index'))
            ->post(route('admin.aparencia.store'), ['file' => UploadedFile::fake()->create('arquivo.txt', 10, 'text/plain')])
            ->assertRedirect(route('admin.aparencia.index'))
            ->assertSessionHasErrors('file');
    }

    public function test_import_surfaces_manifest_errors(): void
    {
        $admin = User::factory()->admin()->create();

        $path = tempnam(sys_get_temp_dir(), 'themebad').'.zip';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('theme.json', json_encode(['name' => 'Quebrado']));
        $zip->close();

        $this->actingAs($admin)
            ->from(route('admin.aparencia.index'))
            ->post(route('admin.aparencia.store'), [
                'file' => new UploadedFile($path, 'quebrado.zip', 'application/zip', null, true),
            ])
            ->assertRedirect(route('admin.aparencia.index'))
            ->assertSessionHasErrors('file');

        $this->assertNull(app(ThemeDiscovery::class)->find('quebrado'));
    }

    private function validZip(string $slug): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'themeok').'.zip';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('theme.json', json_encode([
            'name' => 'Novo Tema',
            'templates' => ['home' => 'index.html', 'page' => 'page.html'],
        ], JSON_UNESCAPED_SLASHES));
        $zip->addFromString('index.html', '<html></html>');
        $zip->addFromString('page.html', '<html></html>');
        $zip->close();

        return new UploadedFile($path, $slug.'.zip', 'application/zip', null, true);
    }
}
