<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O botão explícito de escolha de arquivo (estilo ::file-selector-button da
 * página de Mídia: file:bg-primary-600 …) deve existir nos formulários de
 * upload de Módulos, Aparência e Atualizações (pacote offline .zip).
 */
class UploadFileButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    public function test_modules_page_has_the_styled_file_button(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.modulos.index'))
            ->assertOk()
            ->assertSee('file:bg-primary-600', false);
    }

    public function test_appearance_page_has_the_styled_file_button(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.aparencia.index'))
            ->assertOk()
            ->assertSee('file:bg-primary-600', false);
    }

    public function test_updates_page_has_the_styled_file_button_when_apply_allowed(): void
    {
        config([
            'cms.updater.apply.enabled' => true,
            'cms.updater.apply.package' => true,
            'cms.updater.apply.download' => true,
        ]);

        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)
            ->get(route('admin.configuracoes.atualizacoes.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="package"', $html);
        $this->assertStringContainsString('file:bg-primary-600', $html);
    }

    public function test_media_page_still_has_the_reference_button(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.midia.index'))
            ->assertOk()
            ->assertSee('file:bg-primary-600', false);
    }
}
