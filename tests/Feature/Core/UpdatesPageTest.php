<?php

namespace Tests\Feature\Core;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdatesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    public function test_admin_can_open_the_updates_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.configuracoes.atualizacoes.index'))
            ->assertOk()
            ->assertSee(__('updater.page_title'), false)
            ->assertSee(__('updater.manual'), false);
    }

    public function test_editor_cannot_open_the_updates_page(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->get(route('admin.configuracoes.atualizacoes.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.configuracoes.atualizacoes.index'))
            ->assertRedirect(route('login'));
    }
}
