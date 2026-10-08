<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.configuracoes.index'))->assertRedirect(route('login'));
    }

    public function test_editor_cannot_manage_settings(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('admin.configuracoes.index'))->assertForbidden();
        $this->actingAs($editor)->post(route('admin.configuracoes.update'), [
            'mode' => 'escuro',
            'color' => 'rosa',
        ])->assertForbidden();
    }

    public function test_admin_sees_modes_and_all_colors(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.configuracoes.index'));

        $response->assertOk()
            ->assertSee('name="mode"', false)
            ->assertSee('name="color"', false)
            ->assertSee('value="claro"', false)
            ->assertSee('value="escuro"', false);

        foreach (array_keys(config('cms.admin.colors')) as $slug) {
            $response->assertSee('value="'.$slug.'"', false);
        }

        $response->assertSee('value="claro" checked required', false)
            ->assertSee('value="azul" checked required', false);
    }

    public function test_admin_can_save_mode_and_color(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.update'), ['mode' => 'escuro', 'color' => 'amarelo'])
            ->assertRedirect(route('admin.configuracoes.index'));

        $settings = app(Settings::class);

        $this->assertSame('escuro', $settings->get('admin_mode'));
        $this->assertSame('amarelo', $settings->get('admin_color'));
    }

    public function test_invalid_mode_or_color_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.configuracoes.index'))
            ->post(route('admin.configuracoes.update'), ['mode' => 'unicornio', 'color' => 'azul'])
            ->assertRedirect(route('admin.configuracoes.index'))
            ->assertSessionHasErrors('mode');

        $this->actingAs($admin)
            ->from(route('admin.configuracoes.index'))
            ->post(route('admin.configuracoes.update'), ['mode' => 'claro', 'color' => 'laranja'])
            ->assertRedirect(route('admin.configuracoes.index'))
            ->assertSessionHasErrors('color');

        $this->assertNull(app(Settings::class)->get('admin_mode'));
        $this->assertNull(app(Settings::class)->get('admin_color'));
    }

    public function test_layout_reflects_saved_preferences(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.update'), ['mode' => 'escuro', 'color' => 'roxo'])
            ->assertRedirect(route('admin.configuracoes.index'));

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-admin-color="roxo"', false)
            ->assertSee('data-admin-mode="escuro"', false)
            ->assertSee('class="dark"', false);
    }

    public function test_unknown_stored_color_falls_back_to_default(): void
    {
        $admin = User::factory()->admin()->create();

        app(Settings::class)->set('admin_color', 'inexistente', 'admin');
        app(Settings::class)->set('admin_mode', 'nebuloso', 'admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-admin-color="azul"', false)
            ->assertSee('data-admin-mode="claro"', false)
            ->assertDontSee('class="dark"', false);
    }

    public function test_login_page_uses_saved_preferences(): void
    {
        app(Settings::class)->set('admin_color', 'verde', 'admin');
        app(Settings::class)->set('admin_mode', 'escuro', 'admin');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-admin-color="verde"', false)
            ->assertSee('data-admin-mode="escuro"', false)
            ->assertSee('class="dark"', false);
    }
}
