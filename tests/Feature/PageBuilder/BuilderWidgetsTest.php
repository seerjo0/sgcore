<?php

namespace Tests\Feature\PageBuilder;

use App\Models\User;
use App\Modules\Core\Services\Settings;
use App\Modules\PageBuilder\Models\BuilderRevision;
use App\Modules\PageBuilder\Services\DesignSystem;
use App\Modules\Pages\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuilderWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
        app(Settings::class)->set('site_name', 'Site Teste', 'general');
    }

    private function editor(): User
    {
        return User::factory()->editor()->create();
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

    /**
     * @param  list<array<string, mixed>>  $components
     */
    private function publish(array $components, ?string $html = null): array
    {
        return [
            'html' => $html ?? '<h1>fallback</h1>',
            'css' => 'h2 { color: #0d6efd; }',
            'json' => json_encode(['components' => $components, 'styles' => [], 'assets' => []]),
        ];
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function node(string $type, array $attributes = [], ?string $content = null): array
    {
        $node = [
            'tagName' => 'div',
            'attributes' => ['data-pb-type' => $type, ...$attributes],
        ];

        if ($content !== null) {
            $node['components'] = [['type' => 'text', 'content' => $content]];
        }

        return $node;
    }

    private function heading(string $content): array
    {
        return [
            'tagName' => 'h2',
            'classes' => ['pb-heading'],
            'attributes' => ['data-pb-type' => 'heading'],
            'components' => [['type' => 'text', 'content' => $content]],
        ];
    }

    public function test_video_renders_with_a_safe_source_and_ratio(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publish([
                $this->node('video', ['data-pb-src' => 'https://cdn.example.com/a.mp4', 'data-pb-poster' => '/media/poster.jpg', 'data-pb-aspect' => '4x3']),
            ]))
            ->assertOk();

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('ratio-4x3', false)
            ->assertSee('src="https://cdn.example.com/a.mp4"', false)
            ->assertSee('poster="/media/poster.jpg"', false)
            ->assertSee('controls', false);
    }

    public function test_video_with_a_dangerous_source_is_dropped(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publish([
                $this->node('video', ['data-pb-src' => 'javascript:alert(1)']),
                $this->heading('restou'),
            ]))
            ->assertOk();

        $this->get('/pagina')
            ->assertOk()
            ->assertDontSee('javascript:', false)
            ->assertDontSee('<video', false)
            ->assertSee('restou', false);
    }

    public function test_youtube_embeds_only_a_valid_id_on_the_nocookie_host(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publish([
                $this->node('youtube', ['data-pb-id' => 'dQw4w9WgXcQ']),
            ]))
            ->assertOk();

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('allowfullscreen', false);
    }

    public function test_youtube_with_a_malformed_id_renders_nothing(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publish([
                $this->node('youtube', ['data-pb-id' => 'https://evil.example/x']),
                $this->heading('âncora'),
            ]))
            ->assertOk();

        $this->get('/pagina')
            ->assertOk()
            ->assertDontSee('<iframe', false)
            ->assertDontSee('evil.example', false)
            ->assertSee('âncora', false);
    }

    public function test_blockquote_renders_citation_and_sanitizes_content(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publish([
                $this->node('blockquote', ['data-pb-cite' => 'Author'], 'Frase <strong>forte</strong><script>evil()</script>'),
            ]))
            ->assertOk();

        $page->refresh();
        $html = $page->builder_published['html'];

        $this->assertStringContainsString('<strong>forte</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('blockquote-footer', $html);
        $this->assertStringContainsString('Author', $html);
    }

    public function test_icon_renders_only_allowlisted_paths(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publish([
                $this->node('icon', ['data-pb-icon' => 'star', 'data-pb-size' => 'lg', 'data-pb-variant' => 'danger']),
            ]))
            ->assertOk();

        $page->refresh();
        $html = $page->builder_published['html'];

        $this->assertStringContainsString('pb-icon', $html);
        $this->assertStringContainsString('pb-text-danger', $html);
        $this->assertStringContainsString(config('pagebuilder.icons.star'), $html);
    }

    public function test_icon_rejects_a_type_not_in_the_allowlist(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publish([
                $this->node('icon', ['data-pb-icon' => '<script>']),
                $this->heading('âncora'),
            ]))
            ->assertOk();

        $this->get('/pagina')
            ->assertOk()
            ->assertDontSee('<script>', false)
            ->assertSee('âncora', false);
    }

    public function test_design_system_persists_spacing_and_breakpoints(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->post(route('admin.builder.templates.design'), [
                'spacing' => ['xs' => '0.25rem', 'sm' => '9px', 'md' => '2rem', 'lg' => '4rem'],
                'breakpoints' => ['sm' => '640px', 'md' => '800px', 'lg' => '1024px', 'xl' => '1280px'],
            ])
            ->assertSessionHas('success');

        $tokens = app(DesignSystem::class)->tokens();
        $this->assertSame('9px', $tokens['spacing']['sm']);
        $this->assertSame('800px', $tokens['breakpoints']['md']);

        $css = app(DesignSystem::class)->css();
        $this->assertStringContainsString('--pb-space-sm:9px', $css);
        $this->assertStringContainsString('--pb-bp-md:800px', $css);
    }

    public function test_design_system_falls_back_on_invalid_length(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->post(route('admin.builder.templates.design'), [
                'spacing' => ['md' => 'expression(alert(1))'],
            ]);

        $tokens = app(DesignSystem::class)->tokens();
        $this->assertSame('2rem', $tokens['spacing']['md']);
    }

    public function test_revision_diff_reports_added_and_removed_lines(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publish([
                $this->heading('Primeira'),
            ]))
            ->assertOk();

        $first = BuilderRevision::query()->where('page_id', $page->id)->orderBy('id')->first();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publish([
                $this->heading('Segunda'),
            ]))
            ->assertOk();

        $response = $this->actingAs($editor)
            ->getJson(route('admin.paginas.builder.revisions.diff', [$page, $first]))
            ->assertOk()
            ->assertJsonStructure(['lines', 'truncated']);

        $text = json_encode($response->json('lines'));
        $this->assertStringContainsString('Primeira', $text);
        $this->assertStringContainsString('Segunda', $text);

        $types = array_column($response->json('lines'), 't');
        $this->assertContains('add', $types);
        $this->assertContains('del', $types);
    }

    public function test_revision_diff_is_scoped_to_the_page(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();
        $other = $this->createPage(['slug' => 'outra', 'title' => 'Outra']);

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publish([
                $this->heading('X'),
            ]))
            ->assertOk();

        $revision = BuilderRevision::query()->where('page_id', $page->id)->first();

        $this->actingAs($editor)
            ->getJson(route('admin.paginas.builder.revisions.diff', [$other, $revision]))
            ->assertNotFound();
    }

    public function test_revision_diff_requires_authorization(): void
    {
        $page = $this->createPage();

        $this->getJson(route('admin.paginas.builder.revisions.diff', [$page, 1]))
            ->assertUnauthorized();
    }
}
