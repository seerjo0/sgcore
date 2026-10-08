<?php

namespace Tests\Feature;

use App\Modules\Core\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_default_for_unknown_key(): void
    {
        $settings = $this->app->make(Settings::class);

        $this->assertSame('padrão', $settings->get('chave_inexistente', 'padrão'));
        $this->assertNull($settings->get('chave_inexistente'));
    }

    public function test_set_and_get_roundtrip_for_scalars_and_arrays(): void
    {
        $settings = $this->app->make(Settings::class);

        $settings->set('site_name', 'Meu Site');
        $settings->set('social_links', ['instagram' => 'https://instagram.com/x', 'twitter' => null]);

        $fresh = $this->app->make(Settings::class);

        $this->assertSame('Meu Site', $fresh->get('site_name'));
        $this->assertSame(
            ['instagram' => 'https://instagram.com/x', 'twitter' => null],
            $fresh->get('social_links'),
        );
    }

    public function test_all_filters_by_group(): void
    {
        $settings = $this->app->make(Settings::class);

        $settings->set('site_name', 'Geral', 'general');
        $settings->set('seo_title', 'SEO', 'seo');
        $settings->set('seo_description', 'Descrição', 'seo');

        $seo = $settings->all('seo');

        $this->assertSame(['seo_title' => 'SEO', 'seo_description' => 'Descrição'], $seo);
        $this->assertArrayHasKey('site_name', $settings->all());
    }

    public function test_forget_removes_value(): void
    {
        $settings = $this->app->make(Settings::class);

        $settings->set('temporario', 'valor');
        $settings->forget('temporario');

        $this->assertNull($settings->get('temporario'));
        $this->assertDatabaseMissing('settings', ['key' => 'temporario']);
    }

    public function test_settings_service_is_a_singleton(): void
    {
        $this->assertSame(
            $this->app->make(Settings::class),
            $this->app->make(Settings::class),
        );
    }
}
