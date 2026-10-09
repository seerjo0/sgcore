<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    public function test_default_locale_is_pt_br(): void
    {
        $this->assertSame('pt_BR', config('app.locale'));
        $this->assertSame('pt_BR', config('app.fallback_locale'));
    }

    public function test_login_page_renders_in_portuguese_by_default(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('lang="pt-BR"', false)
            ->assertSee('Painel de controle')
            ->assertSee('Manter conectado');
    }

    public function test_login_page_renders_in_english_when_locale_is_en(): void
    {
        app()->setLocale('en');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('Admin panel')
            ->assertSee('Remember me')
            ->assertDontSee('Painel de controle');
    }

    public function test_admin_dashboard_renders_in_english_when_locale_is_en(): void
    {
        app()->setLocale('en');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Overview of your site.')
            ->assertSee('Sign out');
    }

    public function test_json_translation_keys_resolve_in_both_locales(): void
    {
        app()->setLocale('pt_BR');
        $this->assertSame('Configurações atualizadas.', __('settings.updated'));
        $this->assertSame('Bem-vindo, Maria!', __('dashboard.welcome', ['name' => 'Maria']));

        app()->setLocale('en');
        $this->assertSame('Settings updated.', __('settings.updated'));
        $this->assertSame('Welcome, Maria!', __('dashboard.welcome', ['name' => 'Maria']));
        $this->assertSame('Main (header)', __('menus.location_primary'));
        $this->assertSame('Page not found', __('site.not_found'));
    }

    public function test_flash_messages_follow_the_current_locale(): void
    {
        $admin = User::factory()->admin()->create();

        app()->setLocale('en');

        $this->actingAs($admin)
            ->post(route('admin.usuarios.store'), [
                'name' => 'John Editor',
                'email' => 'john@example.com',
                'role' => 'editor',
                'active' => '1',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
            ])
            ->assertSessionHas('success', 'User created successfully.');
    }
}
