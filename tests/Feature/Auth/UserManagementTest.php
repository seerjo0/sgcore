<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Auth\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    public function test_guest_is_redirected_to_login_from_user_management(): void
    {
        $this->get('/admin/usuarios')->assertRedirect(route('login'));
    }

    public function test_editor_cannot_access_user_management(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get('/admin/usuarios')->assertForbidden();
        $this->actingAs($editor)->get('/admin/usuarios/criar')->assertForbidden();

        $this->actingAs($editor)->post('/admin/usuarios', [
            'name' => 'Intruso',
            'email' => 'intruso@exemplo.com',
            'role' => 'editor',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
            'active' => 1,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'intruso@exemplo.com']);
    }

    public function test_editor_can_access_dashboard(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get('/admin')->assertOk();
    }

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->editor()->create(['name' => 'Maria Editora']);

        $this->actingAs($admin)
            ->get('/admin/usuarios')
            ->assertOk()
            ->assertSee($other->email)
            ->assertSee('Maria Editora');
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/usuarios', [
            'name' => 'João Silva',
            'email' => 'joao@exemplo.com',
            'role' => 'editor',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
            'active' => 1,
        ])->assertRedirect(route('admin.usuarios.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'joao@exemplo.com',
            'name' => 'João Silva',
        ]);

        $created = User::query()->where('email', 'joao@exemplo.com')->firstOrFail();

        $this->assertSame(Role::Editor, $created->role);
        $this->assertTrue($created->active);
        $this->assertTrue(Hash::check('senha-forte-123', $created->password));
    }

    public function test_create_user_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from('/admin/usuarios/criar')
            ->post('/admin/usuarios', [
                'name' => '',
                'email' => 'nao-e-email',
                'role' => 'super',
                'password' => '123',
                'password_confirmation' => '999',
                'active' => 1,
            ])
            ->assertRedirect('/admin/usuarios/criar')
            ->assertSessionHasErrors(['name', 'email', 'role', 'password']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->editor()->create();

        $this->actingAs($admin)->put("/admin/usuarios/{$user->id}", [
            'name' => 'Nome Alterado',
            'email' => 'alterado@exemplo.com',
            'role' => 'admin',
            'active' => 0,
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('admin.usuarios.index'));

        $user->refresh();

        $this->assertSame('Nome Alterado', $user->name);
        $this->assertSame('alterado@exemplo.com', $user->email);
        $this->assertSame(Role::Admin, $user->role);
        $this->assertFalse($user->active);
    }

    public function test_update_can_change_password_and_keeps_old_one_when_blank(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->editor()->create();
        $oldHash = $user->password;

        $this->actingAs($admin)->put("/admin/usuarios/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'editor',
            'active' => 1,
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('admin.usuarios.index'));

        $this->assertSame($oldHash, $user->fresh()->password);

        $this->actingAs($admin)->put("/admin/usuarios/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'editor',
            'active' => 1,
            'password' => 'nova-senha-456',
            'password_confirmation' => 'nova-senha-456',
        ])->assertRedirect(route('admin.usuarios.index'));

        $this->assertTrue(Hash::check('nova-senha-456', $user->fresh()->password));
    }

    public function test_admin_cannot_change_own_role_or_status(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put("/admin/usuarios/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'editor',
            'active' => 0,
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('admin.usuarios.index'));

        $admin->refresh();

        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue($admin->active);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete("/admin/usuarios/{$admin->id}")
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->editor()->create();

        $this->actingAs($admin)
            ->delete("/admin/usuarios/{$user->id}")
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
