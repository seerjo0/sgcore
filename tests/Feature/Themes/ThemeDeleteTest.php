<?php

namespace Tests\Feature\Themes;

use App\Models\User;
use App\Modules\Core\Services\Settings;
use App\Modules\Themes\Models\Content;
use App\Modules\Themes\Services\ThemeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/**
 * Excluir tema (Aparência): só temas importados e não-ativos saem do ar;
 * embutidos e o tema em uso são bloqueados, e um tema quebrado (inválido)
 * pode ser removido — é justamente o remédio para um ZIP ruim.
 */
class ThemeDeleteTest extends TestCase
{
    use RefreshDatabase;

    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();

        $this->storagePath = sys_get_temp_dir().'/sgcore-theme-del-'.uniqid('', true);
        File::makeDirectory($this->storagePath, 0755, true);
        config(['cms.themes.storage_path' => $this->storagePath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storagePath);

        parent::tearDown();
    }

    private function zip(array $entries, string $filename = 'del-tema.zip'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'themezip').'.zip';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return new UploadedFile($path, $filename, 'application/zip', null, true);
    }

    /** @return array<string, string> */
    private function entries(): array
    {
        return [
            'theme.json' => json_encode([
                'name' => 'Tema a Excluir',
                'templates' => ['home' => 'index.html', 'page' => 'page.html'],
                'slots' => ['site.name' => ['type' => 'text', 'scope' => 'global']],
            ], JSON_UNESCAPED_SLASHES),
            'index.html' => '<html><body>del</body></html>',
            'page.html' => '<html><body>pagina</body></html>',
        ];
    }

    public function test_guest_and_editor_cannot_delete_themes(): void
    {
        $this->post(route('admin.aparencia.excluir', 'classic'))
            ->assertRedirect(route('login'));

        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->post(route('admin.aparencia.excluir', 'classic'))
            ->assertForbidden();
    }

    public function test_admin_deletes_imported_theme_and_its_page_slot_values(): void
    {
        $admin = User::factory()->admin()->create();

        $theme = app(ThemeImporter::class)->import($this->zip($this->entries()));
        $this->assertDirectoryExists($theme->path);

        // valores de página no escopo do tema + globais (theme='') — estes
        // últimos devem SOBREVIVER à exclusão.
        Content::query()->create([
            'scope' => 'page', 'theme' => $theme->slug, 'page_id' => 7,
            'values' => ['hero.title' => 'Sumir junto'],
        ]);
        Content::query()->create([
            'scope' => 'global', 'theme' => '', 'page_id' => 0,
            'values' => ['site.name' => 'Ficar'],
        ]);

        $this->actingAs($admin)
            ->from(route('admin.aparencia.index'))
            ->post(route('admin.aparencia.excluir', $theme->slug))
            ->assertRedirect(route('admin.aparencia.index'))
            ->assertSessionHas('success');

        $this->assertDirectoryDoesNotExist($this->storagePath.'/'.$theme->slug);
        $this->assertDatabaseMissing('contents', ['scope' => 'page', 'theme' => $theme->slug]);
        $this->assertDatabaseHas('contents', ['scope' => 'global', 'theme' => '']);
    }

    public function test_active_theme_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();

        $theme = app(ThemeImporter::class)->import($this->zip($this->entries()));
        app(Settings::class)->set('active_theme', $theme->slug, 'general');

        $this->actingAs($admin)
            ->post(route('admin.aparencia.excluir', $theme->slug))
            ->assertSessionHasErrors('theme');

        $this->assertDirectoryExists($theme->path);
    }

    public function test_bundled_theme_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.aparencia.excluir', 'classic'))
            ->assertSessionHasErrors('theme');
    }

    public function test_invalid_imported_theme_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();

        // pasta no storage sem theme.json: discovery a lista como inválida
        File::makeDirectory($this->storagePath.'/quebrado', 0755, true);
        File::put($this->storagePath.'/quebrado/theme.json', '{ json quebrado');

        $this->actingAs($admin)
            ->post(route('admin.aparencia.excluir', 'quebrado'))
            ->assertSessionHas('success');

        $this->assertDirectoryDoesNotExist($this->storagePath.'/quebrado');
    }

    public function test_unknown_slug_is_reported(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.aparencia.excluir', 'fantasma'))
            ->assertSessionHasErrors('theme');
    }
}
