<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use App\Modules\Core\Services\ModuleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class ModuleManagerTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;

    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();

        $this->statePath = sys_get_temp_dir().'/sgcore-modstate-'.uniqid('', true).'.json';
        $this->storagePath = sys_get_temp_dir().'/sgcore-modstorage-'.uniqid('', true);

        config(['cms.modules.state_file' => $this->statePath]);
        config(['cms.modules.storage_path' => $this->storagePath]);
    }

    protected function tearDown(): void
    {
        File::delete($this->statePath);
        File::deleteDirectory($this->storagePath);

        parent::tearDown();
    }

    private function state(): ModuleState
    {
        return app(ModuleState::class);
    }

    /**
     * Reload the route collection like a fresh request would (the module
     * manager reads the state file when routes/web.php is loaded).
     */
    private function reloadRoutes(): void
    {
        $router = $this->app->make('router');
        $router->setRoutes(new RouteCollection);

        require base_path('routes/web.php');

        $router->getRoutes()->refreshNameLookups();
    }

    /**
     * Build a module ZIP with the given entry => content map.
     *
     * @param  array<string, string>  $entries
     */
    private function moduleZip(string $filename, array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'modzip').'.zip';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return new UploadedFile($path, $filename, 'application/zip', null, true);
    }

    /**
     * Standard entries for a valid uploaded module.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function validEntries(array $extra = []): array
    {
        return array_merge([
            'meu_modulo/module.json' => json_encode([
                'name' => 'Meu Módulo',
                'version' => '1.2.0',
                'description' => 'Módulo usado nos testes.',
                'author' => 'QA',
                'requires' => [],
            ], JSON_UNESCAPED_SLASHES),
            'meu_modulo/Providers/MeuModuloServiceProvider.php' => '<?php namespace App\Modules\meu_modulo\Providers; class MeuModuloServiceProvider {}',
        ], $extra);
    }

    /**
     * Write an uploaded module directly on disk (no ZIP), deterministically.
     *
     * @param  list<string>  $requires
     * @param  array<string, string>  $extraFiles  relative path => content
     */
    private function seedUploadedModule(string $slug, string $name, array $requires = [], array $extraFiles = []): void
    {
        $modulePath = $this->storagePath.'/'.$slug;
        File::ensureDirectoryExists($modulePath.'/Providers');
        File::put($modulePath.'/module.json', json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'requires' => $requires,
        ], JSON_UNESCAPED_SLASHES));
        File::put($modulePath.'/Providers/'.Str::studly($slug).'ServiceProvider.php', '<?php');

        foreach ($extraFiles as $relative => $content) {
            $file = $modulePath.'/'.$relative;
            File::ensureDirectoryExists(dirname($file));
            File::put($file, $content);
        }

        $this->state()->rememberInstall($slug, '1.0.0');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/modulos')->assertRedirect(route('login'));
    }

    public function test_editor_cannot_manage_modules(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->get('/admin/modulos')
            ->assertForbidden();

        $this->actingAs($editor)
            ->delete('/admin/modulos/foo')
            ->assertForbidden();
    }

    public function test_index_hides_core_modules_by_default_and_shows_toggle_button(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedUploadedModule('meu_modulo', 'Meu Módulo');

        $this->actingAs($admin)
            ->get('/admin/modulos')
            ->assertOk()
            ->assertSee('Módulos', false)
            ->assertSee('Meu Módulo', false)
            ->assertSee('Instalar módulo', false)
            ->assertSee('Exibir módulos core', false)
            ->assertSee('<section id="core-modules" class="hidden mt-8">', false)
            // o módulo instalado aparece com botão de desativar
            ->assertSee('/admin/modulos/meu_modulo/desativar')
            // core/bundled não têm botão de desativar
            ->assertDontSee('/admin/modulos/themes/desativar')
            ->assertDontSee('/admin/modulos/pages/desativar');
    }

    public function test_bundled_modules_cannot_be_deactivated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/modulos/themes/desativar')
            ->assertRedirect()
            ->assertSessionHasErrors('module');

        $this->actingAs($admin)
            ->post('/admin/modulos/pages/desativar')
            ->assertRedirect()
            ->assertSessionHasErrors('module');

        $this->assertFalse($this->state()->touched());
    }

    public function test_deactivating_and_reactivating_an_uploaded_module_roundtrip(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->seedUploadedModule('meu_modulo', 'Meu Módulo');

        $this->post('/admin/modulos/meu_modulo/desativar')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($this->state()->isInactive('meu_modulo'));

        $this->get('/admin/modulos')
            ->assertSee('Inativo', false);

        $this->post('/admin/modulos/meu_modulo/ativar')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($this->state()->isInactive('meu_modulo'));
    }

    public function test_protected_modules_cannot_be_deactivated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/modulos/core/desativar')
            ->assertRedirect()
            ->assertSessionHasErrors('module');

        $this->assertFalse($this->state()->touched());
    }

    public function test_deactivating_an_uploaded_module_used_by_another_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->seedUploadedModule('modulo_b', 'Módulo B');
        $this->seedUploadedModule('modulo_a', 'Módulo A', ['modulo_b']);

        $this->post('/admin/modulos/modulo_b/desativar')
            ->assertRedirect()
            ->assertSessionHasErrors('module');

        $this->assertFalse($this->state()->isInactive('modulo_b'));
    }

    public function test_deactivating_an_uploaded_module_removes_its_routes_until_reactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->seedUploadedModule('widgets', 'Widgets', [], [
            'routes/web.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/widgets', fn () => response('ok'));\n",
        ]);

        $this->reloadRoutes();

        $this->get('/widgets')->assertOk();

        $this->post('/admin/modulos/widgets/desativar')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->reloadRoutes();

        $this->get('/widgets')->assertNotFound();

        $this->state()->activate('widgets');

        $this->reloadRoutes();

        $this->get('/widgets')->assertOk();
    }

    public function test_valid_zip_is_installed_and_activated_by_default(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/modulos', ['module' => $this->moduleZip('meu-modulo.zip', $this->validEntries())])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDirectoryExists($this->storagePath.'/meu_modulo');
        $this->assertFileExists($this->storagePath.'/meu_modulo/module.json');
        $this->assertNotNull($this->state()->installedAt('meu_modulo'));
        $this->assertFalse($this->state()->isInactive('meu_modulo'));

        $this->get('/admin/modulos')
            ->assertSee('Meu Módulo', false)
            ->assertSee('Instalado', false);
    }

    public function test_zip_zip_slip_entries_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/modulos', ['module' => $this->moduleZip('evil.zip', $this->validEntries([
                '../pwn.php' => '<?php echo "boom";',
            ]))])
            ->assertRedirect()
            ->assertSessionHasErrors('module');

        $this->assertSame([], glob($this->storagePath.'/.import-*') ?: []);
    }

    public function test_zip_without_manifest_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/modulos', ['module' => $this->moduleZip('sem-manifest.zip', [
                'qualquer/Providers/FooServiceProvider.php' => '<?php class Foo {}',
            ])])
            ->assertRedirect()
            ->assertSessionHasErrors('module');

        $this->assertDirectoryDoesNotExist($this->storagePath.'/sem_manifest');
    }

    public function test_zip_with_invalid_manifest_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/modulos', ['module' => $this->moduleZip('invalido.zip', $this->validEntries([
                'meu_modulo/module.json' => json_encode(['description' => 'sem nome'], JSON_UNESCAPED_SLASHES),
            ]))])
            ->assertRedirect()
            ->assertSessionHasErrors('module');

        $this->assertDirectoryDoesNotExist($this->storagePath.'/meu_modulo');
        $this->assertSame([], glob($this->storagePath.'/.import-*') ?: []);
    }

    public function test_zip_without_provider_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/modulos', ['module' => $this->moduleZip('sem-provider.zip', [
                'sem_provider/module.json' => json_encode(['name' => 'Sem Provider'], JSON_UNESCAPED_SLASHES),
            ])])
            ->assertRedirect()
            ->assertSessionHasErrors('module');

        $this->assertDirectoryDoesNotExist($this->storagePath.'/sem_provider');
    }

    public function test_zip_requiring_a_missing_module_is_rejected_and_rolled_back(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/modulos', ['module' => $this->moduleZip('carente.zip', $this->validEntries([
                'meu_modulo/module.json' => json_encode([
                    'name' => 'Carente',
                    'requires' => ['modulo_inexistente'],
                ], JSON_UNESCAPED_SLASHES),
            ]))])
            ->assertRedirect()
            ->assertSessionHasErrors('module');

        $this->assertDirectoryDoesNotExist($this->storagePath.'/meu_modulo');
        $this->assertNull($this->state()->installedAt('meu_modulo'));
    }

    public function test_duplicate_module_slug_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $zip = $this->moduleZip('meu-modulo.zip', $this->validEntries());

        $this->actingAs($admin)->post('/admin/modulos', ['module' => $zip]);
        $this->actingAs($admin)
            ->post('/admin/modulos', ['module' => $this->moduleZip('meu-modulo.zip', $this->validEntries())])
            ->assertRedirect()
            ->assertSessionHasErrors('module');
    }

    public function test_only_inactive_uploaded_modules_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        // Caminho de upload/instalação é coberto por
        // test_valid_zip_is_installed_and_activated_by_default.
        $this->seedUploadedModule('meu_modulo', 'Meu Módulo');

        // módulo recém-instalado/uploadado está ativo → exclusão bloqueada
        $this->delete('/admin/modulos/meu_modulo')
            ->assertRedirect()
            ->assertSessionHasErrors('module');

        // módulo integrado nunca é excluído
        $this->delete('/admin/modulos/pages')
            ->assertRedirect()
            ->assertSessionHasErrors('module');

        $this->post('/admin/modulos/meu_modulo/desativar');

        $this->delete('/admin/modulos/meu_modulo')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDirectoryDoesNotExist($this->storagePath.'/meu_modulo');
        $this->assertNull($this->state()->installedAt('meu_modulo'));
    }
}
