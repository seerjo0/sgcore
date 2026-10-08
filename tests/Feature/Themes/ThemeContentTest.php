<?php

namespace Tests\Feature\Themes;

use App\Models\User;
use App\Modules\Media\Models\Gallery;
use App\Modules\Media\Models\Medium;
use App\Modules\Pages\Models\Page;
use App\Modules\Themes\Services\ContentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    private function createPage(array $overrides = []): Page
    {
        return Page::query()->create(array_merge([
            'title' => 'Página',
            'slug' => 'pagina',
            'status' => 'published',
            'is_home' => false,
            'content' => ['body' => '<p>Corpo</p>'],
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.aparencia.conteudo'))->assertRedirect(route('login'));
    }

    public function test_editor_cannot_manage_theme_content(): void
    {
        $editor = User::factory()->editor()->create();
        $home = $this->createPage(['title' => 'Início', 'slug' => 'inicio', 'is_home' => true]);

        $this->actingAs($editor)->get(route('admin.aparencia.conteudo'))->assertForbidden();

        $this->actingAs($editor)
            ->get(route('admin.aparencia.conteudo.pagina', $home))
            ->assertForbidden();

        $this->actingAs($editor)
            ->post(route('admin.aparencia.conteudo.salvar'), ['slots' => ['site.name' => 'X']])
            ->assertForbidden();

        $this->actingAs($editor)
            ->post(route('admin.aparencia.conteudo.pagina.salvar', $home), ['slots' => ['home.hero_title' => 'X']])
            ->assertForbidden();
    }

    public function test_admin_sees_global_slots_and_page_list(): void
    {
        $admin = User::factory()->admin()->create();
        $sobre = $this->createPage(['title' => 'Sobre', 'slug' => 'sobre']);

        $response = $this->actingAs($admin)->get(route('admin.aparencia.conteudo'));

        $response->assertOk()
            ->assertSee('Slots globais')
            ->assertSee('Logo do site')
            ->assertSee('Nome do site')
            ->assertSee('Slogan')
            ->assertSee('Texto do rodapé')
            ->assertSee('id="media-picker"', false)
            ->assertSee('Sobre')
            ->assertSee(route('admin.aparencia.conteudo.pagina', $sobre), false);
    }

    public function test_admin_sees_page_scoped_slots(): void
    {
        $admin = User::factory()->admin()->create();
        $home = $this->createPage(['title' => 'Início', 'slug' => 'inicio', 'is_home' => true]);

        $this->actingAs($admin)
            ->get(route('admin.aparencia.conteudo.pagina', $home))
            ->assertOk()
            ->assertSee('Título do hero')
            ->assertSee('Subtítulo do hero')
            ->assertSee('Galeria da home')
            ->assertSee('Conteúdo da página')
            ->assertSee('name="slots[home.hero_title]"', false)
            ->assertSee('name="slots[page.body]"', false)
            ->assertSee('Salvar conteúdo da página');
    }

    public function test_admin_saves_global_text_slots(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.aparencia.conteudo.salvar'), [
                'slots' => ['site.name' => '  Meu Site E2E  ', 'site.slogan' => 'Feito com carinho'],
            ])
            ->assertRedirect(route('admin.aparencia.conteudo'));

        $values = app(ContentStore::class)->values('global', '', 0);

        $this->assertSame('Meu Site E2E', $values['site.name']);
        $this->assertSame('Feito com carinho', $values['site.slogan']);
    }

    public function test_empty_text_slot_clears_the_stored_value(): void
    {
        $admin = User::factory()->admin()->create();

        app(ContentStore::class)->put('global', '', 0, ['site.slogan' => 'Antigo']);

        $this->actingAs($admin)
            ->post(route('admin.aparencia.conteudo.salvar'), ['slots' => ['site.slogan' => '']])
            ->assertRedirect(route('admin.aparencia.conteudo'));

        $values = app(ContentStore::class)->values('global', '', 0);

        $this->assertNull($values['site.slogan']);
    }

    public function test_admin_saves_page_scoped_slots(): void
    {
        $admin = User::factory()->admin()->create();
        $home = $this->createPage(['title' => 'Início', 'slug' => 'inicio', 'is_home' => true]);

        $this->actingAs($admin)
            ->post(route('admin.aparencia.conteudo.pagina.salvar', $home), [
                'slots' => ['home.hero_title' => 'Hero salvo', 'home.hero_subtitle' => 'Subtítulo salvo'],
            ])
            ->assertRedirect(route('admin.aparencia.conteudo.pagina', $home));

        $values = app(ContentStore::class)->values('theme', 'classic', $home->id);

        $this->assertSame('Hero salvo', $values['home.hero_title']);
        $this->assertSame('Subtítulo salvo', $values['home.hero_subtitle']);

        $this->assertSame([], app(ContentStore::class)->values('global', '', 0));
    }

    public function test_richtext_slot_is_sanitized_on_save(): void
    {
        $admin = User::factory()->admin()->create();
        $sobre = $this->createPage(['title' => 'Sobre', 'slug' => 'sobre']);

        $this->actingAs($admin)
            ->post(route('admin.aparencia.conteudo.pagina.salvar', $sobre), [
                'slots' => ['page.body' => '<p>Ok</p><script>alert(1)</script><img src="x" onerror="alert(2)"><strong>Sim</strong>'],
            ])
            ->assertRedirect(route('admin.aparencia.conteudo.pagina', $sobre));

        $body = (string) app(ContentStore::class)->values('theme', 'classic', $sobre->id)['page.body'];

        $this->assertStringContainsString('<p>Ok</p>', $body);
        $this->assertStringContainsString('<strong>Sim</strong>', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onerror', $body);
    }

    public function test_image_slot_stores_media_id_and_renders_its_url(): void
    {
        $admin = User::factory()->admin()->create();
        $medium = Medium::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.aparencia.conteudo.salvar'), [
                'slots' => ['site.logo' => (string) $medium->id],
            ])
            ->assertRedirect(route('admin.aparencia.conteudo'));

        $values = app(ContentStore::class)->values('global', '', 0);

        $this->assertSame($medium->id, $values['site.logo']);

        $this->get('/')
            ->assertOk()
            ->assertSee('/media/'.$medium->path);
    }

    public function test_valid_gallery_slot_is_stored(): void
    {
        $admin = User::factory()->admin()->create();
        $gallery = Gallery::factory()->create();
        $home = $this->createPage(['title' => 'Início', 'slug' => 'inicio', 'is_home' => true]);

        $this->actingAs($admin)
            ->post(route('admin.aparencia.conteudo.pagina.salvar', $home), [
                'slots' => ['home.gallery' => (string) $gallery->id],
            ])
            ->assertRedirect(route('admin.aparencia.conteudo.pagina', $home));

        $values = app(ContentStore::class)->values('theme', 'classic', $home->id);

        $this->assertSame($gallery->id, $values['home.gallery']);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $home = $this->createPage(['title' => 'Início', 'slug' => 'inicio', 'is_home' => true]);

        $this->actingAs($admin)
            ->from(route('admin.aparencia.conteudo'))
            ->post(route('admin.aparencia.conteudo.salvar'), [
                'slots' => ['site.logo' => '999999'],
            ])
            ->assertRedirect(route('admin.aparencia.conteudo'))
            ->assertSessionHasErrors('slots.site.logo');

        $this->actingAs($admin)
            ->from(route('admin.aparencia.conteudo.pagina', $home))
            ->post(route('admin.aparencia.conteudo.pagina.salvar', $home), [
                'slots' => ['home.gallery' => 'abc'],
            ])
            ->assertSessionHasErrors('slots.home.gallery');

        $this->actingAs($admin)
            ->post(route('admin.aparencia.conteudo.salvar'), [
                'slots' => ['site.name' => str_repeat('a', 501)],
            ])
            ->assertSessionHasErrors('slots.site.name');

        $this->assertSame([], app(ContentStore::class)->values('global', '', 0));
    }

    public function test_unknown_slot_ids_are_ignored(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.aparencia.conteudo.salvar'), [
                'slots' => ['fantasma' => 'x', 'site.name' => 'Ok'],
            ])
            ->assertRedirect(route('admin.aparencia.conteudo'));

        $values = app(ContentStore::class)->values('global', '', 0);

        $this->assertSame(['site.name' => 'Ok'], $values);
    }

    public function test_saved_global_content_renders_on_the_public_site(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createPage(['title' => 'Início', 'slug' => 'inicio', 'is_home' => true]);

        $this->actingAs($admin)
            ->post(route('admin.aparencia.conteudo.salvar'), [
                'slots' => ['site.name' => 'Nome Via Rota'],
            ])
            ->assertRedirect(route('admin.aparencia.conteudo'));

        $this->get('/')->assertOk()->assertSee('Nome Via Rota');
    }

    public function test_text_slot_with_html_is_escaped_on_render(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createPage(['title' => 'Início', 'slug' => 'inicio', 'is_home' => true]);

        $this->actingAs($admin)
            ->post(route('admin.aparencia.conteudo.salvar'), [
                'slots' => ['site.slogan' => '<script>alert(1)</script>'],
            ])
            ->assertRedirect(route('admin.aparencia.conteudo'));

        $this->get('/')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }
}
