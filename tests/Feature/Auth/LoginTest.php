<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    public function test_login_page_is_displayed_for_guests(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Painel de controle')
            ->assertSee('type="password"', false);
    }

    public function test_authenticated_user_is_redirected_away_from_login_page(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get('/admin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_guest_is_redirected_to_login_from_admin_area(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->admin()->create();

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_redirects_to_intended_admin_url(): void
    {
        $user = User::factory()->admin()->create();

        $this->get('/admin/usuarios')->assertRedirect(route('login'));

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.usuarios.index'));
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->admin()->inactive()->create();

        $this->from('/admin/login')
            ->post('/admin/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('inativa', session('errors')->first());
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->admin()->create();

        $this->from('/admin/login')
            ->post('/admin/login', [
                'email' => $user->email,
                'password' => 'senha-errada',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('inválidos', session('errors')->first());
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->post('/admin/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_deactivated_session_is_logged_out(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user);

        $user->update(['active' => false]);

        $this->get('/admin')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
