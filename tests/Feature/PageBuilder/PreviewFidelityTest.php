<?php

namespace Tests\Feature\PageBuilder;

use App\Modules\Core\Services\Settings;
use App\Modules\PageBuilder\Services\ComponentRegistry;
use App\Modules\PageBuilder\Services\ProjectExtractor;
use App\Modules\PageBuilder\Services\RenderContext;
use App\Modules\PageBuilder\Services\Renderer;
use App\Modules\Pages\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the contract the editor's serializeProject() relies on:
 * ProjectExtractor must read the model's `content` (text edits) and the
 * style attributes (style-manager edits) from the serialized project, and
 * Renderer must ship the page's builder_custom_css (converted theme CSS)
 * inside the preview document.
 */
class PreviewFidelityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    private function project(array $components): array
    {
        return ['components' => $components];
    }

    public function test_text_edits_serialized_by_the_editor_survive_extraction_and_render(): void
    {
        // Exactly what getProjectData() contains after a double-click edit +
        // serializeProject() flush: the text child carries the NEW content and
        // the heading mirrors it into data-pb-content.
        $project = $this->project([[
            'type' => 'pb-heading',
            'tagName' => 'h2',
            'attributes' => ['data-pb-type' => 'heading', 'data-pb-content' => 'TÍTULO EDITADO'],
            'components' => [['type' => 'text', 'content' => 'TÍTULO EDITADO']],
        ]]);

        $tree = app(ProjectExtractor::class)->extract($project);
        $html = app(ComponentRegistry::class)->renderTree($tree, new RenderContext(app(Settings::class)));

        $this->assertStringContainsString('TÍTULO EDITADO', $html);
        $this->assertStringNotContainsString('Conteúdo que importa', $html);
    }

    public function test_style_manager_edits_survive_extraction_and_render(): void
    {
        $project = $this->project([[
            'type' => 'pb-text',
            'tagName' => 'p',
            'attributes' => ['data-pb-type' => 'text', 'style' => 'color: rgb(255, 0, 0);'],
            'components' => [['type' => 'text', 'content' => 'vermelho']],
        ]]);

        $tree = app(ProjectExtractor::class)->extract($project);
        $html = app(ComponentRegistry::class)->renderTree($tree, new RenderContext(app(Settings::class)));

        $this->assertStringContainsString('style="color: rgb(255, 0, 0);"', $html);
    }

    public function test_preview_document_ships_the_page_custom_css(): void
    {
        $page = Page::query()->create([
            'title' => 'Viva', 'slug' => 'viva', 'status' => 'published',
            'content' => ['body' => ''],
            'builder_custom_css' => '.hero{background:#0b1220}',
        ]);

        $document = app(Renderer::class)->previewDocument($page, '<section class="hero">oi</section>', '');

        $this->assertStringContainsString('.hero{background:#0b1220}', $document);
    }
}
