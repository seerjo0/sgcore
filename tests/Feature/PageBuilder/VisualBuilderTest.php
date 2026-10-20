<?php

namespace Tests\Feature\PageBuilder;

use App\Models\User;
use App\Modules\Core\Services\Settings;
use App\Modules\Media\Models\Medium;
use App\Modules\PageBuilder\Models\BuilderRevision;
use App\Modules\PageBuilder\Models\BuilderTemplate;
use App\Modules\Pages\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisualBuilderTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function project(array $components): array
    {
        return [
            'components' => $components,
            'styles' => [],
            'assets' => [],
        ];
    }

    /**
     * A page-based GrapesJS project, the shape the editor sends since the page
     * manager became core (GrapesJS >= 0.21). Components live under the first
     * page frame's wrapper instead of a flat top-level `components` key.
     *
     * @param  list<array<string, mixed>>  $components
     * @return array<string, mixed>
     */
    private function pageProject(array $components): array
    {
        return [
            'pages' => [
                [
                    'id' => 'page-1',
                    'type' => 'main',
                    'frames' => [
                        [
                            'id' => 'frame-1',
                            'component' => ['type' => 'wrapper', 'components' => $components],
                        ],
                    ],
                ],
            ],
            'styles' => [],
            'assets' => [],
        ];
    }

    /**
     * A heading node carrying inline text, as the GrapesJS editor stores it.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function heading(array $attributes = [], ?string $content = 'Construído'): array
    {
        return [
            'tagName' => 'h2',
            'classes' => ['pb-heading'],
            'attributes' => ['data-pb-type' => 'heading', ...$attributes],
            'components' => [['type' => 'text', 'content' => $content ?? '']],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @return array{html: string, css: string, json: string}
     */
    private function publishPayload(array $components, string $html = '<h1>fallback</h1>'): array
    {
        return [
            'html' => $html,
            'css' => 'h2 { color: #0d6efd; }',
            'json' => json_encode($this->project($components)),
        ];
    }

    public function test_typed_publish_renders_through_the_component_registry(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                $this->heading(['data-pb-level' => '3']),
            ]))
            ->assertOk()
            ->assertJson(['ok' => true, 'version' => 2]);

        $page->refresh();

        $this->assertIsArray($page->builder_published['components'] ?? null);
        $this->assertStringContainsString('<h3', $page->builder_published['html']);
        $this->assertStringContainsString('pb-heading', $page->builder_published['html']);

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('Construído', false)
            ->assertSee('pb-main', false);
    }

    public function test_page_based_project_renders_through_the_component_registry(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), [
                'html' => '<h1>fallback</h1>',
                'css' => 'h2 { color: #0d6efd; }',
                'json' => json_encode($this->pageProject([
                    $this->heading(['data-pb-level' => '3']),
                ])),
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'version' => 2]);

        $page->refresh();

        $this->assertIsArray($page->builder_published['components'] ?? null);
        $this->assertStringContainsString('<h3', $page->builder_published['html']);
        $this->assertStringContainsString('pb-heading', $page->builder_published['html']);

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('Construído', false)
            ->assertSee('pb-main', false);
    }

    public function test_autosave_stores_a_page_based_draft(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.autosave', $page), [
                'data' => json_encode($this->pageProject([
                    $this->heading(content: 'Rascunho em página'),
                ])),
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $page->refresh();

        $this->assertIsArray($page->builder_draft);
        $this->assertArrayHasKey('pages', $page->builder_draft);
        $this->assertStringContainsString('Rascunho em página', json_encode($page->builder_draft, JSON_UNESCAPED_UNICODE));
    }

    public function test_autosave_rejects_a_payload_without_components_or_pages(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.autosave', $page), ['data' => '{"dataSources":[]}'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data');
    }

    public function test_html_component_renders_sanitized_raw_markup(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $node = [
            'tagName' => 'div',
            'attributes' => [
                'data-pb-type' => 'html',
                'data-pb-code' => '<h3 class="pb-x">Olá <script>alert(1)</script><a href="javascript:evil()">clique</a></h3>',
            ],
        ];

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([$node], html: '<div></div>'))
            ->assertOk();

        $page->refresh();

        $this->assertStringContainsString('<h3 class="pb-x">Olá', $page->builder_published['html']);
        $this->assertStringNotContainsString('<script', $page->builder_published['html']);
        $this->assertStringNotContainsString('javascript:', $page->builder_published['html']);
    }

    public function test_unregistered_component_types_are_skipped(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $evil = [
            'tagName' => 'div',
            'attributes' => ['data-pb-type' => 'evil_widget'],
            'components' => [['type' => 'text', 'content' => 'Payload estranho']],
        ];

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                $evil,
                $this->heading(content: 'Só o título'),
            ]))
            ->assertOk();

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('Só o título', false)
            ->assertDontSee('evil_widget')
            ->assertDontSee('Payload estranho');
    }

    public function test_publish_fails_when_no_valid_component_survives_validation(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        // level "9" violates the heading component's rules, so it is dropped.
        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                $this->heading(['data-pb-level' => '9']),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('html');

        $page->refresh();
        $this->assertNull($page->builder_published);
    }

    public function test_xss_vectors_in_component_props_are_neutralized(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                $this->heading(content: '<script>alert(1)</script><strong>ok</strong>'),
                [
                    'tagName' => 'img',
                    'attributes' => [
                        'data-pb-type' => 'image',
                        'src' => 'javascript:alert(1)',
                        'alt' => 'inofensivo',
                    ],
                ],
            ]))
            ->assertOk();

        $page->refresh();
        $html = $page->builder_published['html'];

        $this->assertStringContainsString('<strong>ok</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_publish_creates_versions_and_revisions(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                $this->heading(content: 'Primeira'),
            ]))
            ->assertOk()
            ->assertJson(['version' => 2]);

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                $this->heading(content: 'Segunda'),
            ]))
            ->assertOk()
            ->assertJson(['version' => 3]);

        $page->refresh();
        $this->assertSame(3, (int) $page->builder_version);
        $this->assertSame(2, BuilderRevision::query()->where('page_id', $page->id)->count());

        $this->actingAs($editor)
            ->getJson(route('admin.paginas.builder.revisions', $page))
            ->assertOk()
            ->assertJsonPath('items.0.version', 3)
            ->assertJsonPath('items.1.version', 2);
    }

    public function test_restore_revision_returns_content_and_draft(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                $this->heading(content: 'Versão antiga'),
            ]))
            ->assertOk();

        $first = BuilderRevision::query()->where('page_id', $page->id)->orderBy('id')->first();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                $this->heading(content: 'Versão nova'),
            ]))
            ->assertOk();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.revisions.restore', [$page, $first]))
            ->assertOk()
            ->assertJson(['ok' => true, 'version' => 4]);

        $page->refresh();
        $this->assertStringContainsString('Versão antiga', $page->builder_published['html']);
        $this->assertStringContainsString('Versão antiga', (string) json_encode($page->builder_draft, JSON_UNESCAPED_UNICODE));

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('Versão antiga', false)
            ->assertDontSee('Versão nova');
    }

    public function test_dynamic_bindings_resolve_during_render(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();
        $medium = Medium::factory()->create(['path' => '2026/foto.png']);

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                $this->heading(content: '{site:name} | {page:title} | {year}'),
                [
                    'tagName' => 'img',
                    'attributes' => [
                        'data-pb-type' => 'image',
                        'src' => '{media:'.$medium->id.'}',
                        'alt' => 'da capa',
                    ],
                ],
            ]))
            ->assertOk();

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('Site Teste | Página | '.now()->year, false)
            ->assertSee('/media/2026/foto.png', false);
    }

    public function test_design_system_applies_tokens_and_purges_page_cache(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                $this->heading(content: 'Com cache'),
            ]))
            ->assertOk();

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('--pb-primary:#4f46e5', false);

        $this->actingAs($editor)
            ->post(route('admin.builder.templates.design'), [
                'colors' => ['primary' => '#ff0000'],
                'fonts' => ['body' => 'Georgia, serif'],
            ])
            ->assertSessionHas('success');

        $this->assertSame('#ff0000', app(Settings::class)->get('design_system')['colors']['primary']);

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('--pb-primary:#ff0000', false)
            ->assertSee('Com cache', false);
    }

    public function test_templates_replace_the_chrome_when_enabled(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                $this->heading(content: 'Conteúdo da página'),
            ]))
            ->assertOk();

        $this->actingAs($editor)
            ->postJson(route('admin.builder.templates.publish', 'header'), $this->publishPayload([
                $this->heading(['data-pb-type' => 'heading'], 'Topo customizado'),
            ]))
            ->assertOk();

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('pb-brand', false);

        $this->actingAs($editor)
            ->postJson(route('admin.builder.templates.toggle', 'header'), ['enabled' => true])
            ->assertOk()
            ->assertJson(['enabled' => true]);

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('Topo customizado', false)
            ->assertDontSee('pb-brand', false);
    }

    public function test_not_found_template_replaces_the_theme_404(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->postJson(route('admin.builder.templates.publish', 'not_found'), $this->publishPayload([
                $this->heading(content: 'Página perdida do construtor'),
            ]))
            ->assertOk();

        $this->get('/rota-inexistente')
            ->assertNotFound()
            ->assertDontSee('Página perdida do construtor');

        $this->actingAs($editor)
            ->postJson(route('admin.builder.templates.toggle', 'not_found'), ['enabled' => true])
            ->assertOk();

        $this->get('/rota-inexistente')
            ->assertNotFound()
            ->assertSee('Página perdida do construtor', false);

        $this->actingAs($editor)
            ->postJson(route('admin.builder.templates.toggle', 'not_found'), ['enabled' => false])
            ->assertOk();

        $this->get('/rota-inexistente')
            ->assertNotFound()
            ->assertDontSee('Página perdida do construtor');
    }

    public function test_template_publish_requires_registered_components(): void
    {
        $editor = $this->editor();

        $legacy = [
            'components' => [
                ['tagName' => 'h1', 'components' => [['type' => 'text', 'content' => 'Sem tipo']]],
            ],
        ];

        $this->actingAs($editor)
            ->postJson(route('admin.builder.templates.publish', 'footer'), [
                'html' => '<h1>Sem tipo</h1>',
                'css' => 'h1 { color: #0d6efd; }',
                'json' => json_encode($legacy),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('json');
    }

    public function test_templates_index_lists_the_three_types(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->get(route('admin.builder.templates.index'))
            ->assertOk()
            ->assertSee(__('pagebuilder.templates_title'))
            ->assertSee(__('pagebuilder.template_header'))
            ->assertSee(__('pagebuilder.template_footer'))
            ->assertSee(__('pagebuilder.template_not_found'));
    }

    public function test_template_editor_opens_with_the_shared_config(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->get(route('admin.builder.templates.edit', 'header'))
            ->assertOk()
            ->assertSee('pageBuilderConfig', false)
            ->assertSee('"mode":"template"', false)
            ->assertSee('media-picker', false);
    }

    public function test_templates_screen_requires_authorization(): void
    {
        $this->get(route('admin.builder.templates.index'))
            ->assertRedirect(route('login'));

        $this->postJson(route('admin.builder.templates.toggle', 'header'), ['enabled' => true])
            ->assertUnauthorized();

        $editor = $this->editor();
        BuilderTemplate::query()->create(['type' => 'header', 'enabled' => false]);

        $this->actingAs($editor)
            ->postJson(route('admin.builder.templates.toggle', 'header'), ['enabled' => true])
            ->assertOk()
            ->assertJson(['enabled' => true]);
    }

    public function test_revisions_are_scoped_to_the_page(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();
        $other = $this->createPage(['slug' => 'outra', 'title' => 'Outra']);

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([
                $this->heading(content: 'Da primeira'),
            ]))
            ->assertOk();

        $revision = BuilderRevision::query()->where('page_id', $page->id)->first();

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.revisions.restore', [$other, $revision]))
            ->assertNotFound();
    }

    public function test_nested_layout_nodes_keep_their_children(): void
    {
        $editor = $this->editor();
        $page = $this->createPage();

        $section = [
            'tagName' => 'section',
            'classes' => ['pb-section', 'py-5'],
            'attributes' => ['data-pb-type' => 'section'],
            'components' => [
                [
                    'tagName' => 'div',
                    'classes' => ['container'],
                    'attributes' => ['data-pb-type' => 'container'],
                    'components' => [
                        [
                            'tagName' => 'div',
                            'classes' => ['row', 'g-3'],
                            'attributes' => ['data-pb-type' => 'row'],
                            'components' => [
                                [
                                    'tagName' => 'div',
                                    'classes' => ['col'],
                                    'attributes' => ['data-pb-type' => 'column'],
                                    'components' => [$this->heading(content: 'Dentro da grade')],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($editor)
            ->postJson(route('admin.paginas.builder.publish', $page), $this->publishPayload([$section]))
            ->assertOk();

        $page->refresh();

        $this->assertStringContainsString('<section', $page->builder_published['html']);
        $this->assertStringContainsString('Dentro da grade', $page->builder_published['html']);

        $this->get('/pagina')
            ->assertOk()
            ->assertSee('Dentro da grade', false)
            ->assertSee('pb-section py-5', false);
    }
}
