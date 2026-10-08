<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Services\AdminPreferences;
use App\Modules\Core\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPathTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    private function settings(): Settings
    {
        return $this->app->make(Settings::class);
    }

    public function test_editor_cannot_change_the_admin_path(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->post(route('admin.configuracoes.update'), ['mode' => 'claro', 'color' => 'azul', 'admin_path' => 'x'])
            ->assertForbidden();
    }

    public function test_admin_sees_the_admin_path_field(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.configuracoes.index'))
            ->assertOk()
            ->assertSee('Caminho do admin')
            ->assertSee('name="admin_path"', false);
    }

    public function test_valid_admin_path_is_persisted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.update'), [
                'mode' => 'claro',
                'color' => 'azul',
                'admin_path' => 'Painel-X', // normalizado para minúsculas
            ])
            // o redirect ja aponta pro novo endereco (o antigo morre na hora)
            ->assertRedirect(url('/painel-x/configuracoes'));

        $this->assertSame('painel-x', $this->settings()->get(AdminPreferences::KEY_PATH));
        $this->assertSame('painel-x', $this->app->make(AdminPreferences::class)->adminPath());
    }

    public function test_invalid_admin_paths_are_rejected_and_keep_the_previous_value(): void
    {
        $admin = User::factory()->admin()->create();
        $this->settings()->set(AdminPreferences::KEY_PATH, 'meu-painel', 'admin');

        $invalid = [
            'Caminho Invalido', // maiúsculas/espaço já normalizados ainda têm espaço
            'foo/bar',
            'a_b',
            str_repeat('a', 31),
            'login',
            'instalar',
            'media',
            'theme-assets',
        ];

        foreach ($invalid as $index => $path) {
            $this->actingAs($admin)
                ->post(route('admin.configuracoes.update'), [
                    'mode' => 'claro',
                    'color' => 'azul',
                    'admin_path' => $path,
                ])
                ->assertSessionHasErrors('admin_path');

            $this->assertSame('meu-painel', $this->settings()->get(AdminPreferences::KEY_PATH), "iteração #{$index}");
        }
    }

    public function test_clearing_the_field_restores_the_default_path(): void
    {
        $admin = User::factory()->admin()->create();
        $this->settings()->set(AdminPreferences::KEY_PATH, 'backend', 'admin');

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.update'), [
                'mode' => 'claro',
                'color' => 'azul',
                'admin_path' => '',
            ])
            ->assertRedirect(route('admin.configuracoes.index'));

        $this->assertNull($this->settings()->get(AdminPreferences::KEY_PATH));
        $this->assertSame('admin', $this->app->make(AdminPreferences::class)->adminPath());
    }

    public function test_path_sanitize_rules(): void
    {
        $preferences = $this->app->make(AdminPreferences::class);

        $this->assertSame('backend', $preferences->sanitizePath(' Backend '));
        $this->assertSame('painel-01', $preferences->sanitizePath('painel-01'));
        $this->assertSame('x', $preferences->sanitizePath('x'));

        $this->assertNull($preferences->sanitizePath(''));
        $this->assertNull($preferences->sanitizePath('a b'));
        $this->assertNull($preferences->sanitizePath('a/b'));
        $this->assertNull($preferences->sanitizePath('-inicio'));
        $this->assertNull($preferences->sanitizePath('fim-'));
        $this->assertNull($preferences->sanitizePath('instalar'));
        $this->assertNull($preferences->sanitizePath(str_repeat('a', 31)));
    }
}
