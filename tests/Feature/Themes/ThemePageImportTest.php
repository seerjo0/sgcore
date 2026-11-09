<?php

namespace Tests\Feature\Themes;

use App\Models\User;
use App\Modules\PageBuilder\Services\ComponentRegistry;
use App\Modules\PageBuilder\Services\Components\ComponentNode;
use App\Modules\Pages\Models\Page;
use App\Modules\Themes\Services\ThemePageImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePageImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    private function importer(): ThemePageImporter
    {
        return app(ThemePageImporter::class);
    }

    public function test_it_lists_the_templates_a_theme_exposes(): void
    {
        $templates = $this->importer()->templates('classic');

        $this->assertContains('home', $templates);
        $this->assertContains('page', $templates);
    }

    public function test_import_maps_theme_markup_to_builder_blocks(): void
    {
        $project = $this->importer()->import('classic', 'home');

        $registry = app(ComponentRegistry::class);

        $this->assertTrue($registry->isProject($project));

        $tree = $registry->extract($project);
        $types = $this->collectTypes($tree);

        $this->assertContains('section', $types);
        $this->assertContains('heading', $types);

        $json = json_encode($project, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Bem-vindo', $json);
        // Theme chrome is dropped: the builder renders its own header/footer/menus.
        $this->assertStringNotContainsString('site-header', $json);
        $this->assertStringNotContainsString('nav-list', $json);
    }

    public function test_import_drops_the_document_head_instead_of_leaking_it_as_html_blocks(): void
    {
        // libxml flattens the wrapped template, so head-only tags can end up as
        // direct children of the import root. They used to become `html` blocks
        // with the raw `<meta>/<title>/<link>` markup in data-pb-code — the junk
        // that made converted pages look broken in the editor.
        foreach (['classic', 'modern', 'gallery'] as $slug) {
            foreach ($this->importer()->templates($slug) as $template) {
                $json = json_encode($this->importer()->import($slug, $template), JSON_UNESCAPED_UNICODE);

                $this->assertStringNotContainsString('<meta', $json, "{$slug}/{$template} leaked a <meta> block");
                $this->assertStringNotContainsString('<title', $json, "{$slug}/{$template} leaked a <title> block");
                $this->assertStringNotContainsString('<link', $json, "{$slug}/{$template} leaked a <link> block");
                $this->assertStringNotContainsString('<style', $json, "{$slug}/{$template} leaked a <style> block");
            }
        }
    }

    public function test_imported_nodes_carry_the_grapesjs_component_types(): void
    {
        // loadProjectData resolves models from the JSON `type` (isComponent
        // never runs there), so every custom block must come pre-typed with the
        // ids the editor registers, or imported pages open without traits.
        $json = json_encode($this->importer()->import('classic', 'home'), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('"type":"pb-section"', $json);
        $this->assertStringContainsString('"type":"pb-container"', $json);
        $this->assertStringContainsString('"type":"pb-heading"', $json);
        $this->assertStringContainsString('"type":"pb-text"', $json);
        // Built-ins keep their own ids (the editor does not register pb-* for
        // them); the wrapper frame root is already built-in.
        $this->assertStringNotContainsString('"type":"pb-image"', $json);
        $this->assertStringContainsString('"type":"image"', $json);

        // Custom-code blocks are typed pb-html too, and show the same canvas
        // placeholder the editor's own HTML block carries.
        $pageJson = json_encode($this->importer()->import('classic', 'page'), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('"type":"pb-html"', $pageJson);
        $this->assertStringContainsString('Escreva seu HTML na aba Propriedades', $pageJson);
    }

    /**
     * @param  iterable<ComponentNode>  $nodes
     * @return list<string>
     */
    private function collectTypes(iterable $nodes): array
    {
        $types = [];

        foreach ($nodes as $node) {
            $types[] = $node->type;
            $types = array_merge($types, $this->collectTypes($node->children));
        }

        return $types;
    }

    public function test_import_to_page_creates_an_editable_draft(): void
    {
        $page = $this->importer()->importToPage('classic', 'page', 'Chegando');

        $this->assertNotNull($page->id);
        $this->assertSame('draft', $page->status);
        $this->assertIsArray($page->builder_draft);
        $this->assertArrayHasKey('pages', $page->builder_draft);
        $this->assertSame('Chegando', $page->title);
    }

    public function test_admin_can_import_a_page_from_the_appearance_ui(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.aparencia.importar'), [
                'theme' => 'classic',
                'template' => 'home',
                'title' => 'Importada',
            ]);

        $page = Page::query()->where('title', 'Importada')->firstOrFail();
        $this->assertSame('draft', $page->status);
        $this->assertIsArray($page->builder_draft);

        $response->assertRedirect(route('admin.paginas.builder.index', $page));
    }

    public function test_the_conversion_route_requires_the_appearance_permission(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->post(route('admin.aparencia.importar'), [
                'theme' => 'classic',
                'template' => 'home',
                'title' => 'Não deveria',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('pages', ['title' => 'Não deveria']);
    }

    public function test_a_converted_page_publishes_and_renders_as_real_markup(): void
    {
        // End-to-end of the reported flow: convert a theme template, publish
        // the draft like the editor does, and render it publicly. Nothing may
        // come out as escaped HTML source (`&lt;div`), the "raw code" symptom.
        // `is_home` false + count>0 is not guaranteed across tests, so publish
        // through the real route with a slug the fallback can serve.
        $admin = User::factory()->admin()->create();
        Page::query()->create(['title' => 'Semente', 'slug' => 'semente', 'status' => 'draft', 'content' => ['body' => '']]);
        $page = $this->importer()->importToPage('modern', 'home', 'Convertida');

        $this->actingAs($admin)
            ->postJson(route('admin.paginas.builder.publish', $page), [
                'html' => '<section></section>',
                'css' => 'h1{}',
                'json' => json_encode($page->builder_draft),
            ])
            ->assertOk();

        $page->update(['status' => 'published']);

        $this->get('/'.$page->slug)
            ->assertOk()
            ->assertSee('Conteúdo que importa')
            ->assertSee('<h1', false)
            ->assertDontSee('&lt;div', false)
            ->assertDontSee('&lt;h1', false);
    }
}
