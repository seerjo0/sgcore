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
}
