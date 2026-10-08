<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Services\Settings;
use App\Modules\Media\Models\Medium;
use App\Modules\Pages\Models\Page;
use App\Modules\Themes\Services\ContentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSiteSettingsTest extends TestCase
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

    private function settings(): Settings
    {
        return $this->app->make(Settings::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.configuracoes.index'))->assertRedirect(route('login'));

        $this->post(route('admin.configuracoes.update'), ['mode' => 'claro', 'color' => 'azul'])
            ->assertRedirect(route('login'));
    }

    public function test_editor_cannot_manage_settings(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('admin.configuracoes.index'))->assertForbidden();

        $this->actingAs($editor)
            ->post(route('admin.configuracoes.update'), ['mode' => 'claro', 'color' => 'azul'])
            ->assertForbidden();
    }

    public function test_admin_sees_site_and_seo_sections(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.configuracoes.index'));

        $response->assertOk()
            ->assertSee('>Site<', false)
            ->assertSee('>SEO<', false)
            ->assertSee('name="site_name"', false)
            ->assertSee('name="site_logo"', false)
            ->assertSee('name="site_favicon"', false)
            ->assertSee('name="seo_title"', false)
            ->assertSee('name="seo_description"', false)
            ->assertSee('name="seo_og_image"', false)
            ->assertSee('id="media-picker"', false);
    }

    public function test_site_and_seo_values_are_persisted(): void
    {
        $admin = User::factory()->admin()->create();
        $logo = Medium::factory()->create(['path' => '2026/10/logo-set.png']);
        $favicon = Medium::factory()->create(['path' => '2026/10/fav-set.png']);
        $og = Medium::factory()->create(['path' => '2026/10/og-set.png']);

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.update'), [
                'mode' => 'claro',
                'color' => 'azul',
                'site_name' => 'Meu Portal',
                'site_logo' => $logo->id,
                'site_favicon' => $favicon->id,
                'seo_title' => 'Título SEO padrão',
                'seo_description' => 'Descrição SEO padrão',
                'seo_og_image' => $og->id,
            ])
            ->assertRedirect(route('admin.configuracoes.index'))
            ->assertSessionHas('success');

        $settings = $this->settings();

        $this->assertSame('Meu Portal', $settings->get('site_name'));
        $this->assertSame($logo->id, $settings->get('site_logo'));
        $this->assertSame($favicon->id, $settings->get('site_favicon'));
        $this->assertSame('Título SEO padrão', $settings->get('seo_title'));
        $this->assertSame('Descrição SEO padrão', $settings->get('seo_description'));
        $this->assertSame($og->id, $settings->get('seo_og_image'));
    }

    public function test_invalid_media_ids_are_rejected_and_keep_previous_values(): void
    {
        $admin = User::factory()->admin()->create();
        $logo = Medium::factory()->create(['path' => '2026/10/logo-keep.png']);
        $this->settings()->set('site_logo', $logo->id, 'site');
        $this->settings()->set('seo_og_image', $logo->id, 'seo');

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.update'), [
                'mode' => 'claro',
                'color' => 'azul',
                'site_logo' => 999999,
                'seo_og_image' => 999998,
            ])
            ->assertSessionHasErrors(['site_logo', 'seo_og_image']);

        $this->assertSame($logo->id, $this->settings()->get('site_logo'));
        $this->assertSame($logo->id, $this->settings()->get('seo_og_image'));
    }

    public function test_clearing_media_fields_stores_null(): void
    {
        $admin = User::factory()->admin()->create();
        $logo = Medium::factory()->create(['path' => '2026/10/logo-clear.png']);
        $this->settings()->set('site_logo', $logo->id, 'site');
        $this->settings()->set('site_favicon', $logo->id, 'site');
        $this->settings()->set('seo_og_image', $logo->id, 'seo');

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.update'), [
                'mode' => 'claro',
                'color' => 'azul',
                'site_logo' => '',
                'site_favicon' => '',
                'seo_og_image' => '',
                'seo_title' => '',
            ])
            ->assertRedirect(route('admin.configuracoes.index'));
        $this->assertNull($this->settings()->get('site_logo'));
        $this->assertNull($this->settings()->get('site_favicon'));
        $this->assertNull($this->settings()->get('seo_og_image'));
        $this->assertNull($this->settings()->get('seo_title'));
    }

    public function test_empty_site_name_falls_back_to_app_name(): void
    {
        $admin = User::factory()->admin()->create();
        $this->settings()->set('site_name', 'Anterior', 'general');

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.update'), [
                'mode' => 'claro',
                'color' => 'azul',
                'site_name' => '',
            ])
            ->assertRedirect(route('admin.configuracoes.index'));

        $this->assertNull($this->settings()->get('site_name'));
        $this->assertSame(config('app.name'), $this->settings()->get('site_name', config('app.name')));
    }

    public function test_favicon_and_seo_defaults_render_on_the_public_site(): void
    {
        $this->createPage(['title' => 'Início', 'slug' => 'inicio', 'is_home' => true]);
        $favicon = Medium::factory()->create(['path' => '2026/10/fav-render.png']);
        $og = Medium::factory()->create(['path' => '2026/10/og-render.png']);

        $settings = $this->settings();
        $settings->set('site_name', 'Portal Render', 'general');
        $settings->set('seo_title', 'SEO Home', 'seo');
        $settings->set('seo_description', 'Descrição global', 'seo');
        $settings->set('site_favicon', $favicon->id, 'site');
        $settings->set('seo_og_image', $og->id, 'seo');

        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="icon"', false)
            ->assertSee('/media/2026/10/fav-render.png', false)
            ->assertSee('property="og:site_name" content="Portal Render"', false)
            ->assertSee('property="og:type" content="website"', false)
            ->assertSee('property="og:url"', false)
            ->assertSee('/media/2026/10/og-render.png', false)
            ->assertSee('name="description" content="Descrição global"', false);
    }

    public function test_page_seo_values_override_the_settings_defaults(): void
    {
        $sobre = $this->createPage([
            'title' => 'Sobre',
            'slug' => 'sobre',
            'seo_title' => 'Sobre Nós Extra',
            'seo_description' => 'Descrição da página',
        ]);

        $defaultOg = Medium::factory()->create(['path' => '2026/10/og-default.png']);
        $pageOg = Medium::factory()->create(['path' => '2026/10/og-pagina.png']);
        $sobre->update(['seo_og_image' => $pageOg->id]);

        $settings = $this->settings();
        $settings->set('site_name', 'Portal', 'general');
        $settings->set('seo_title', 'SEO Home', 'seo');
        $settings->set('seo_description', 'Descrição global', 'seo');
        $settings->set('seo_og_image', $defaultOg->id, 'seo');

        $this->get('/sobre')
            ->assertOk()
            ->assertSee('<title>Sobre Nós Extra · Portal</title>', false)
            ->assertSee('name="description" content="Descrição da página"', false)
            ->assertSee('/media/2026/10/og-pagina.png', false)
            ->assertDontSee('/media/2026/10/og-default.png', false);
    }

    public function test_page_without_seo_falls_back_to_settings_defaults(): void
    {
        $this->createPage(['title' => 'Contato', 'slug' => 'contato']);

        $settings = $this->settings();
        $settings->set('site_name', 'Portal', 'general');
        $settings->set('seo_description', 'Descrição global', 'seo');

        $this->get('/contato')
            ->assertOk()
            ->assertSee('<title>Contato · Portal</title>', false)
            ->assertSee('name="description" content="Descrição global"', false);
    }

    public function test_site_logo_setting_fills_the_slot_when_nothing_is_stored(): void
    {
        $this->createPage(['title' => 'Início', 'slug' => 'inicio', 'is_home' => true]);
        $logo = Medium::factory()->create(['path' => '2026/10/logo-setting.png']);
        $this->settings()->set('site_logo', $logo->id, 'site');

        $this->get('/')
            ->assertOk()
            ->assertSee('/media/2026/10/logo-setting.png', false);
    }

    public function test_stored_slot_beats_the_site_logo_setting(): void
    {
        $this->createPage(['title' => 'Início', 'slug' => 'inicio', 'is_home' => true]);

        $settingLogo = Medium::factory()->create(['path' => '2026/10/logo-ajustado-setting.png']);
        $slotLogo = Medium::factory()->create(['path' => '2026/10/logo-slot-ganha.png']);

        $this->settings()->set('site_logo', $settingLogo->id, 'site');
        $this->app->make(ContentStore::class)->put('global', '', 0, ['site.logo' => $slotLogo->id]);

        $this->get('/')
            ->assertOk()
            ->assertSee('/media/2026/10/logo-slot-ganha.png', false)
            ->assertDontSee('/media/2026/10/logo-ajustado-setting.png', false);
    }
}
