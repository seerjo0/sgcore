<?php

namespace Tests\Feature\Themes;

use App\Modules\Core\Services\Settings;
use App\Modules\Pages\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();

        $this->storagePath = sys_get_temp_dir().'/sgcore-public-'.uniqid('', true);
        File::makeDirectory($this->storagePath, 0755, true);

        config(['cms.themes.storage_path' => $this->storagePath]);

        app(Settings::class)->set('site_name', 'Site Público', 'general');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storagePath);

        parent::tearDown();
    }

    private function createPage(array $overrides = []): Page
    {
        return Page::query()->create(array_merge([
            'title' => 'Página',
            'slug' => 'pagina',
            'status' => 'published',
            'is_home' => false,
            'content' => ['body' => '<p>Corpo público</p>'],
        ], $overrides));
    }

    public function test_home_renders_the_active_theme(): void
    {
        $this->createPage(['title' => 'Início', 'slug' => 'inicio', 'is_home' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('theme-classic')
            ->assertSee('Site Público');
    }

    public function test_home_renders_without_a_home_page(): void
    {
        $this->get('/')->assertOk()->assertSee('theme-classic');
    }

    public function test_published_page_resolves_by_slug(): void
    {
        $this->createPage(['title' => 'Sobre', 'slug' => 'sobre']);

        $this->get('/sobre')
            ->assertOk()
            ->assertSee('Sobre')
            ->assertSee('Corpo público');
    }

    public function test_draft_page_returns_404(): void
    {
        $this->createPage(['title' => 'Rascunho', 'slug' => 'rascunho', 'status' => 'draft']);

        $this->get('/rascunho')->assertNotFound();
    }

    public function test_unknown_slug_returns_thematic_404(): void
    {
        $response = $this->get('/nao-existe');

        $response->assertNotFound();
        $response->assertSee('Página não encontrada');
        $response->assertSee('error-code');
    }

    public function test_multi_segment_paths_return_404(): void
    {
        $this->get('/qualquer/coisa')->assertNotFound();
    }

    public function test_system_routes_are_not_shadowed_by_the_catch_all(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/up')->assertOk();
    }

    public function test_theme_stylesheet_is_served_from_theme_assets(): void
    {
        $response = $this->get('/theme-assets/classic/styles.css');

        $response->assertOk();
        $this->assertStringContainsString('text/css', (string) $response->headers->get('Content-Type'));
        $this->assertStringEndsWith(
            'classic'.DIRECTORY_SEPARATOR.'styles.css',
            $response->baseResponse->getFile()->getPathname(),
        );
    }

    public function test_asset_traversal_is_rejected(): void
    {
        $this->get('/theme-assets/classic/..%2F..%2F..%2F.env')->assertNotFound();
    }

    public function test_unknown_theme_asset_returns_404(): void
    {
        $this->get('/theme-assets/nao-existe/styles.css')->assertNotFound();
    }

    public function test_switching_the_active_theme_changes_the_rendered_site(): void
    {
        app(Settings::class)->set('active_theme', 'modern', 'general');

        $this->get('/')->assertOk()->assertSee('theme-modern');
    }
}
