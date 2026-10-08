<?php

namespace Tests\Feature\Themes;

use App\Modules\Core\Services\Settings;
use App\Modules\Media\Models\Gallery;
use App\Modules\Media\Models\GalleryItem;
use App\Modules\Media\Models\Medium;
use App\Modules\Pages\Models\Menu;
use App\Modules\Pages\Models\MenuItem;
use App\Modules\Pages\Models\Page;
use App\Modules\Themes\Services\ContentStore;
use App\Modules\Themes\Services\ThemeRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemeRendererTest extends TestCase
{
    use RefreshDatabase;

    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();

        $this->storagePath = sys_get_temp_dir().'/sgcore-renderer-'.uniqid('', true);
        File::makeDirectory($this->storagePath, 0755, true);

        config(['cms.themes.storage_path' => $this->storagePath]);

        app(Settings::class)->set('site_name', 'Empresa Teste', 'general');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storagePath);

        parent::tearDown();
    }

    private function render(?Page $page, string $template = 'page', int $status = 200): string
    {
        return app(ThemeRenderer::class)->render($page, $template, $status)->getContent();
    }

    private function homePage(): Page
    {
        return Page::query()->create([
            'title' => 'Início',
            'slug' => 'inicio',
            'status' => 'published',
            'is_home' => true,
            'content' => ['body' => '<p>Corpo do início</p>'],
        ]);
    }

    private function standardPage(array $overrides = []): Page
    {
        return Page::query()->create(array_merge([
            'title' => 'Sobre Nós',
            'slug' => 'sobre',
            'status' => 'published',
            'is_home' => false,
            'content' => ['body' => '<p>Conteúdo original da página</p>'],
        ], $overrides));
    }

    public function test_home_uses_site_name_and_hero_defaults(): void
    {
        $html = $this->render(null, 'home');

        $this->assertStringContainsString('theme-classic', $html);
        $this->assertStringContainsString('Empresa Teste', $html);
        $this->assertStringContainsString('Bem-vindo', $html);
        $this->assertStringContainsString('/theme-assets/classic/styles.css', $html);
        $this->assertStringNotContainsString('data-cms-slot', $html);
    }

    public function test_page_title_and_body_come_from_the_page(): void
    {
        $page = $this->standardPage();

        $html = $this->render($page, 'page');

        $this->assertStringContainsString('<h1 class="page-title">Sobre Nós</h1>', $html);
        $this->assertStringContainsString('<p>Conteúdo original da página</p>', $html);
    }

    public function test_body_is_sanitized_before_rendering(): void
    {
        $page = $this->standardPage([
            'content' => ['body' => '<p>Antes</p><script>alert(1)</script><p>Depois</p>'],
        ]);

        $html = $this->render($page, 'page');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<p>Antes</p>', $html);
        $this->assertStringContainsString('<p>Depois</p>', $html);
    }

    public function test_stored_values_override_page_and_global_fallbacks(): void
    {
        $page = $this->standardPage();

        app(ContentStore::class)->put('global', '', 0, ['site.name' => 'Nome Customizado']);
        app(ContentStore::class)->put('theme', 'classic', $page->id, [
            'page.title' => 'Título Customizado',
            'page.body' => '<p>Corpo customizado</p>',
        ]);

        $html = $this->render($page, 'page');

        $this->assertStringContainsString('Nome Customizado', $html);
        $this->assertStringContainsString('Título Customizado', $html);
        $this->assertStringContainsString('<p>Corpo customizado</p>', $html);
        $this->assertStringNotContainsString('Sobre Nós', $html);
    }

    public function test_image_slot_sets_background_and_keeps_placeholder_when_empty(): void
    {
        $home = $this->homePage();

        $withImage = $this->render($home, 'home');
        $this->assertStringNotContainsString('background-image: url', $withImage);

        app(ContentStore::class)->put('theme', 'classic', $home->id, [
            'home.hero_image' => '2026/10/hero.jpg',
        ]);

        $withImage = $this->render($home, 'home');
        $this->assertStringContainsString('background-image: url', $withImage);
        $this->assertStringContainsString('/media/2026/10/hero.jpg', $withImage);
    }

    public function test_gallery_slot_renders_figures_from_the_gallery_module(): void
    {
        $home = $this->homePage();

        $media = Medium::factory()->create(['path' => '2026/10/galeria.jpg', 'alt' => 'Foto da galeria']);

        $gallery = Gallery::query()->create(['title' => 'Trabalhos']);
        GalleryItem::query()->create([
            'gallery_id' => $gallery->id,
            'media_id' => $media->id,
            'sort_order' => 0,
            'caption' => 'Primeira legenda',
        ]);

        app(ContentStore::class)->put('theme', 'classic', $home->id, [
            'home.gallery' => (string) $gallery->id,
        ]);

        $html = $this->render($home, 'home');

        $this->assertStringContainsString('<figure>', $html);
        $this->assertStringContainsString('src="/media/2026/10/galeria.jpg"', $html);
        $this->assertStringContainsString('<figcaption>Primeira legenda</figcaption>', $html);

        app(ContentStore::class)->put('theme', 'classic', $home->id, ['home.gallery' => '9999']);

        $this->assertStringNotContainsString('<figure>', $this->render($home, 'home'));
    }

    public function test_menus_render_nested_items_and_hide_unpublished_pages(): void
    {
        $this->homePage();
        $published = $this->standardPage();
        $draft = $this->standardPage(['title' => 'Rascunho', 'slug' => 'rascunho', 'status' => 'draft']);

        $menu = Menu::query()->create(['title' => 'Principal', 'location' => 'primary']);

        $root = MenuItem::query()->create([
            'menu_id' => $menu->id, 'label' => 'Sobre', 'type' => 'page',
            'page_id' => $published->id, 'sort_order' => 0,
        ]);
        MenuItem::query()->create([
            'menu_id' => $menu->id, 'label' => 'Rascunho', 'type' => 'page',
            'page_id' => $draft->id, 'sort_order' => 1,
        ]);
        MenuItem::query()->create([
            'menu_id' => $menu->id, 'label' => 'Externo', 'type' => 'custom',
            'url' => 'https://example.com', 'parent_id' => $root->id, 'sort_order' => 0,
        ]);

        $html = $this->render(null, 'home');

        $this->assertStringContainsString('<a href="/sobre">Sobre</a>', $html);
        $this->assertStringContainsString('<a href="https://example.com">Externo</a>', $html);
        $this->assertStringNotContainsString('>Rascunho</a>', $html);
        $this->assertStringNotContainsString('data-cms-menu', $html);
    }

    public function test_seo_head_is_rebuilt_from_page_and_settings(): void
    {
        app(Settings::class)->set('seo_title', 'Título Global', 'seo');
        app(Settings::class)->set('seo_description', 'Descrição global.', 'seo');

        $page = $this->standardPage([
            'seo_title' => 'Sobre',
            'seo_description' => 'Página sobre a empresa.',
        ]);

        $html = $this->render($page, 'page');

        $this->assertStringContainsString('<title>Sobre · Empresa Teste</title>', $html);
        $this->assertStringContainsString('name="description" content="Página sobre a empresa."', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringNotContainsString('Título Global', $html);

        $home = $this->render(null, 'home');
        $this->assertStringContainsString('<title>Título Global · Empresa Teste</title>', $home);
        $this->assertStringContainsString('name="description" content="Descrição global."', $home);
    }

    public function test_not_found_template_returns_404(): void
    {
        $response = app(ThemeRenderer::class)->render(null, '404', 404);

        $this->assertSame(404, $response->getStatusCode());

        $html = $response->getContent();
        $this->assertStringContainsString('error-code', $html);
        $this->assertStringContainsString('<title>Página não encontrada · Empresa Teste</title>', $html);
    }

    public function test_text_slot_values_are_escaped(): void
    {
        app(ContentStore::class)->put('global', '', 0, [
            'site.name' => '<script>alert(1)</script>',
        ]);

        $html = $this->render(null, 'home');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
