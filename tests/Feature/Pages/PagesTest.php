<?php

namespace Tests\Feature\Pages;

use App\Models\User;
use App\Modules\Media\Models\Medium;
use App\Modules\Pages\Models\Menu;
use App\Modules\Pages\Models\MenuItem;
use App\Modules\Pages\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.paginas.index'))->assertRedirect(route('login'));
    }

    public function test_editor_can_list_and_create_pages(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('admin.paginas.index'))->assertOk();

        $this->actingAs($editor)
            ->post(route('admin.paginas.store'), [
                'title' => 'Nossa História',
                'slug' => '',
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.paginas.index'));

        $this->assertDatabaseHas('pages', ['slug' => 'nossa-historia', 'status' => 'draft']);
    }

    public function test_title_and_status_are_required(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.paginas.create'))
            ->post(route('admin.paginas.store'), ['title' => '', 'slug' => '', 'status' => ''])
            ->assertRedirect(route('admin.paginas.create'))
            ->assertSessionHasErrors(['title', 'status']);
    }

    public function test_auto_slug_gets_a_numeric_suffix_when_taken(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['Contato', 'Contato'] as $title) {
            $this->actingAs($admin)
                ->post(route('admin.paginas.store'), ['title' => $title, 'slug' => '', 'status' => 'draft']);
        }

        $slugs = Page::orderBy('id')->pluck('slug')->all();

        $this->assertSame(['contato', 'contato-2'], $slugs);
    }

    public function test_typed_slug_cannot_be_reserved_or_taken(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.paginas.create'))
            ->post(route('admin.paginas.store'), ['title' => 'Escuro', 'slug' => 'admin', 'status' => 'draft'])
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin)
            ->post(route('admin.paginas.store'), ['title' => 'Sobre', 'slug' => 'sobre', 'status' => 'draft']);

        $this->actingAs($admin)
            ->from(route('admin.paginas.create'))
            ->post(route('admin.paginas.store'), ['title' => 'Sobre dois', 'slug' => 'sobre', 'status' => 'draft'])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Page::count());
    }

    public function test_body_is_sanitized_on_save(): void
    {
        $admin = User::factory()->admin()->create();

        $dirty = '<p>Olá <strong>mundo</strong></p>'
            .'<script>alert(1)</script>'
            .'<a href="javascript:alert(1)" onclick="evil()">clique</a>'
            .'<a href="&#106;avascript:alert(2)">entidade</a>'
            .'<img src="/foto.jpg" onerror="evil()">'
            .'<a href="https://exemplo.com/pagina" title="ok">link bom</a>';

        $this->actingAs($admin)
            ->post(route('admin.paginas.store'), [
                'title' => 'Corpo',
                'slug' => 'corpo',
                'status' => 'draft',
                'content' => ['body' => $dirty],
            ]);

        $body = Page::where('slug', 'corpo')->firstOrFail()->body();

        $this->assertStringContainsString('<p>Olá <strong>mundo</strong></p>', $body);
        $this->assertStringContainsString('src="/foto.jpg"', $body);
        $this->assertStringContainsString('href="https://exemplo.com/pagina"', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('ascript', $body);
    }

    public function test_update_page_fields_and_seo(): void
    {
        $admin = User::factory()->admin()->create();
        $page = Page::create(['title' => 'Antigo', 'slug' => 'antigo', 'status' => 'draft']);

        $this->actingAs($admin)
            ->put(route('admin.paginas.update', $page), [
                'title' => 'Novo Título',
                'slug' => 'novo-slug',
                'status' => 'published',
                'seo_title' => 'Título SEO',
                'seo_description' => 'Descrição SEO',
                'content' => ['body' => '<p>Conteúdo novo</p>'],
            ])
            ->assertRedirect(route('admin.paginas.index'));

        $page->refresh();

        $this->assertSame('Novo Título', $page->title);
        $this->assertSame('novo-slug', $page->slug);
        $this->assertSame('published', $page->status);
        $this->assertSame('Título SEO', $page->seo_title);
        $this->assertNotNull($page->published_at);
        $this->assertStringContainsString('Conteúdo novo', $page->body());
    }

    public function test_home_page_is_unique_and_can_be_moved(): void
    {
        $admin = User::factory()->admin()->create();

        $first = Page::create(['title' => 'Primeira', 'slug' => 'primeira', 'status' => 'published', 'is_home' => true]);
        $second = Page::create(['title' => 'Segunda', 'slug' => 'segunda', 'status' => 'published', 'is_home' => true]);

        // Model-level: the controller enforces the singleton on save.
        $this->actingAs($admin)
            ->post(route('admin.paginas.store'), [
                'title' => 'Terceira',
                'slug' => 'terceira',
                'status' => 'draft',
                'is_home' => '1',
            ]);

        $this->assertSame(1, Page::where('is_home', true)->count());
        $this->assertFalse($first->fresh()->is_home);

        // Creating a NON-home page must not demote the current home.
        $this->actingAs($admin)
            ->post(route('admin.paginas.store'), [
                'title' => 'Normal',
                'slug' => 'normal',
                'status' => 'draft',
                'is_home' => '0',
            ]);

        $this->assertSame(1, Page::where('is_home', true)->count());
        $this->assertTrue(Page::where('slug', 'terceira')->firstOrFail()->is_home);
        $this->assertFalse($second->fresh()->is_home);
    }

    public function test_og_image_can_be_set_and_validated(): void
    {
        $admin = User::factory()->admin()->create();
        $medium = Medium::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.paginas.store'), [
                'title' => 'Com imagem',
                'slug' => 'com-imagem',
                'status' => 'draft',
                'seo_og_image' => $medium->id,
            ]);

        $this->assertSame($medium->id, Page::where('slug', 'com-imagem')->firstOrFail()->seo_og_image);

        $this->actingAs($admin)
            ->from(route('admin.paginas.create'))
            ->post(route('admin.paginas.store'), [
                'title' => 'Imagem ruim',
                'slug' => 'imagem-ruim',
                'status' => 'draft',
                'seo_og_image' => 999999,
            ])
            ->assertSessionHasErrors('seo_og_image');
    }

    public function test_edit_form_renders_the_single_mode_picker(): void
    {
        $admin = User::factory()->admin()->create();
        $page = Page::create(['title' => 'Form', 'slug' => 'form', 'status' => 'draft']);

        $this->actingAs($admin)
            ->get(route('admin.paginas.edit', $page))
            ->assertOk()
            ->assertSee('data-mode="single"', false)
            ->assertSee('id="seo_og_image"', false)
            ->assertSee('media-picker', false);
    }

    public function test_delete_page_clears_menu_item_references(): void
    {
        $admin = User::factory()->admin()->create();
        $menu = Menu::create(['title' => 'Principal', 'location' => 'primary']);
        $page = Page::create(['title' => 'Apagar', 'slug' => 'apagar', 'status' => 'published']);

        $item = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Apagar',
            'type' => 'page',
            'page_id' => $page->id,
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.paginas.destroy', $page))
            ->assertRedirect(route('admin.paginas.index'));

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
        $this->assertNull($item->fresh()->page_id);
    }
}
