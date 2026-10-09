<?php

namespace Tests\Feature\PageBuilder;

use App\Models\User;
use App\Modules\Core\Services\Settings;
use App\Modules\Pages\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
        app(Settings::class)->set('site_name', 'Site Teste', 'general');
    }

    /**
     * Minimal GrapesJS-like project payload (the shape the editor stores).
     *
     * @return array<string, mixed>
     */
    private function project(?string $marker = null): array
    {
        return [
            'components' => [
                ['tagName' => 'h1', 'classes' => ['display-6'], 'components' => [['type' => 'text', 'content' => $marker ?? 'Olá mundo']]],
            ],
            'styles' => ['h1 { color: #0d6efd; }'],
            'assets' => [],
        ];
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
     * @return array{html: string, css: string, json: string}
     */
    private function publishPayload(array $overrides = []): array
    {
        return array_merge([
            'html' => '<h1 class="display-6">Olá mundo</h1>',
            'css' => 'h1 { color: #0d6efd; }',
            'json' => json_encode($this->project()),
        ], $overrides);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $page = $this->createPage();

        $this->get(route('admin.paginas.builder.index', $page))->assertRedirect(route('login'));
        $this->post(route('admin.paginas.builder.autosave', $page), ['data' => '{}'])->assertRedirect(route('login'));
        $this->post(route('admin.paginas.builder.publish', $page), $this->publishPayload())->assertRedirect(route('login'));
    }

    public function test_editor_can_open_the_builder(): void
    {
        $editor = User::factory()->editor()->create();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->get(route('admin.paginas.builder.index', $page))
            ->assertOk()
            ->assertSee('pageBuilderConfig', false)
            ->assertSee('media-picker', false)
            ->assertSee('pb-media-target', false);
    }

    public function test_autosave_stores_a_draft(): void
    {
        $editor = User::factory()->editor()->create();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.autosave', $page), [
                'data' => json_encode($this->project('Draft marcador')),
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $page->refresh();

        $this->assertNotNull($page->builder_draft);
        $this->assertArrayHasKey('components', $page->builder_draft);
        $this->assertStringContainsString('Draft marcador', json_encode($page->builder_draft));
    }

    public function test_the_editor_restores_the_saved_draft(): void
    {
        $editor = User::factory()->editor()->create();
        $page = $this->createPage(['builder_draft' => $this->project('Draft carregado')]);

        $this->actingAs($editor)
            ->get(route('admin.paginas.builder.index', $page))
            ->assertOk()
            ->assertSee('Draft carregado', false);
    }

    public function test_autosave_rejects_malformed_project(): void
    {
        $editor = User::factory()->editor()->create();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.autosave', $page), ['data' => '{"no_components":true}'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data');
    }

    public function test_publish_persists_sanitized_output(): void
    {
        $editor = User::factory()->editor()->create();
        $page = $this->createPage();

        $dirty = '<h1 class="display-6">Olá mundo</h1>'
            .'<script>alert(1)</script>'
            .'<a href="javascript:alert(1)" onclick="evil()">clique</a>'
            .'<a href="&#106;avascript:alert(2)">entidade</a>'
            .'<img src="data:image/png;base64,AAAA" onerror="evil()">'
            .'<a href="https://exemplo.com/pagina" target="_blank">link bom</a>';

        $dirtyCss = '@import url(http://evil.example/x.css);'
            .'h1 { color: #0d6efd; width: expression(alert(1)); }'
            .'a { background: url("javascript:alert(1)"); }';

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                'html' => $dirty,
                'css' => $dirtyCss,
            ]))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $page->refresh();
        $published = $page->builder_published;

        $this->assertIsArray($published);
        $this->assertStringContainsString('<h1 class="display-6">Olá mundo</h1>', $published['html']);
        $this->assertStringContainsString('href="https://exemplo.com/pagina"', $published['html']);
        $this->assertStringNotContainsString('<script', $published['html']);
        $this->assertStringNotContainsString('alert(1)', $published['html']);
        $this->assertStringNotContainsString('onclick', $published['html']);
        $this->assertStringNotContainsString('onerror', $published['html']);
        $this->assertStringNotContainsString('javascript:', $published['html']);
        $this->assertStringNotContainsString('data:image', $published['html']);
        $this->assertStringNotContainsString('@import', $published['css']);
        $this->assertStringNotContainsString('javascript:', $published['css']);
        $this->assertStringContainsString('color: #0d6efd', $published['css']);

        // Publishing also promotes the same project to the draft.
        $this->assertArrayHasKey('components', $page->builder_draft);
    }

    public function test_published_page_renders_the_builder_layout(): void
    {
        $editor = User::factory()->editor()->create();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload())
            ->assertOk();

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('Olá mundo', false)
            ->assertSee('vendor/pagebuilder/bootstrap.min.css', false)
            ->assertSee('Site Teste')
            ->assertSee('lang="pt-BR"', false)
            ->assertSee('©', false)
            ->assertSee('pb-main', false);
    }

    public function test_unpublished_draft_does_not_change_the_public_version(): void
    {
        $editor = User::factory()->editor()->create();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload(['html' => '<h1>Versão A</h1>']))
            ->assertOk();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.autosave', $page), [
                'data' => json_encode($this->project('Versão B')),
            ])
            ->assertOk();

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('Versão A')
            ->assertDontSee('Versão B');

        $page->refresh();
        $this->assertStringContainsString('Versão A', $page->builder_published['html']);
    }

    public function test_publish_replaces_the_previous_public_version(): void
    {
        $editor = User::factory()->editor()->create();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload(['html' => '<h1>Versão A</h1>']))
            ->assertOk();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload(['html' => '<h1>Versão B</h1>']))
            ->assertOk();

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('Versão B')
            ->assertDontSee('Versão A');
    }

    public function test_builder_layout_also_renders_the_home_page(): void
    {
        $editor = User::factory()->editor()->create();
        $page = $this->createPage(['slug' => 'inicio', 'is_home' => true]);

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload())
            ->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSee('Olá mundo', false)
            ->assertSee('pb-main', false);
    }

    public function test_page_without_builder_output_falls_back_to_the_theme(): void
    {
        $page = $this->createPage();

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('theme-classic', false)
            ->assertDontSee('pb-main', false);
    }

    public function test_a_page_whose_builder_content_was_fully_removed_falls_back(): void
    {
        $editor = User::factory()->editor()->create();
        $page = $this->createPage();

        // Only scripts and styles — nothing survives the sanitizer.
        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                'html' => '<script>alert(1)</script><style>p{color:red}</style>',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('html');

        $page->refresh();
        $this->assertNull($page->builder_published);
    }
}
