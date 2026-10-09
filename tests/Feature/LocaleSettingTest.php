<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Services\LocalePreferences;
use App\Modules\Core\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    private function storeLocale(string $locale): void
    {
        $this->app->make(Settings::class)->set(LocalePreferences::KEY_LOCALE, $locale, 'general');
    }

    public function test_default_remains_pt_br_when_no_locale_is_stored(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('lang="pt-BR"', false)
            ->assertSee('Painel de controle');
    }

    public function test_login_and_dashboard_render_in_the_chosen_language(): void
    {
        $this->storeLocale('en');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('Admin panel')
            ->assertSee('Remember me');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Sign out');
    }

    public function test_invalid_stored_locale_falls_back_to_the_default(): void
    {
        $this->storeLocale('de');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('lang="pt-BR"', false)
            ->assertSee('Painel de controle');
    }
}
