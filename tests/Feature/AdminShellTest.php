<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Media\Models\Medium;
use App\Modules\Pages\Models\Menu;
use App\Modules\Pages\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    public function test_dashboard_reports_content_counts(): void
    {
        $admin = User::factory()->admin()->create();

        Page::query()->create(['title' => 'A', 'slug' => 'a', 'status' => 'published', 'is_home' => false]);
        Page::query()->create(['title' => 'B', 'slug' => 'b', 'status' => 'draft', 'is_home' => false]);
        Medium::factory()->count(3)->create();
        Menu::query()->create(['title' => 'Principal', 'location' => 'primary']);

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('userCount', 1)
            ->assertViewHas('pageCount', 2)
            ->assertViewHas('mediaCount', 3)
            ->assertViewHas('menuCount', 1);

        $response->assertSee(__('dashboard.pages_total'))
            ->assertSee(__('dashboard.media_total'))
            ->assertSee(__('dashboard.menus_total'));
    }

    public function test_quick_access_links_every_admin_area(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.aparencia.conteudo'), false)
            ->assertSee(route('admin.configuracoes.index'), false)
            ->assertSee(route('admin.builder.templates.index'), false)
            ->assertSee(__('dashboard.quick_access'));
    }

    public function test_quick_access_and_nav_respect_editor_gates(): void
    {
        $editor = User::factory()->editor()->create();

        $response = $this->actingAs($editor)
            ->get(route('admin.dashboard'))
            ->assertOk()
            // Editor areas (sidebar + quick-access) are present...
            ->assertSee(route('admin.paginas.index'), false)
            ->assertSee(route('admin.menus.index'), false)
            ->assertSee(route('admin.builder.templates.index'), false);

        // ...admin-only routes never leak to editors.
        $response->assertDontSee(route('admin.aparencia.index'), false)
            ->assertDontSee(route('admin.configuracoes.index'), false)
            ->assertDontSee(route('admin.usuarios.index'), false)
            ->assertDontSee(route('admin.modulos.index'), false);
    }

    public function test_layout_renders_the_responsive_sidebar_shell(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('id="admin-sidebar"', false)
            ->assertSee('data-open="false"', false)
            ->assertSee('id="admin-sidebar-toggle"', false)
            ->assertSee('id="admin-sidebar-overlay"', false)
            ->assertSee(__('nav.open_menu'));
    }
}
