<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Services\AdminPreferences;
use App\Modules\Core\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Tests\TestCase;

/**
 * The admin prefix is resolved when routes are loaded (routes/web.php), so
 * these tests reload the route collection after pointing the config/setting
 * to a custom path — the same thing a fresh request does in production.
 */
class AdminPathRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    /**
     * Point the admin path at $path and reload every route, like a new
     * application boot would do.
     */
    private function useAdminPath(string $path): void
    {
        config(['cms.admin.default_path' => $path]);

        $router = $this->app->make('router');
        $router->setRoutes(new RouteCollection);

        require base_path('routes/web.php');

        // The framework refreshes named-route lookups after loading routes;
        // a manual reload must do the same or route('…') breaks.
        $router->getRoutes()->refreshNameLookups();
    }

    public function test_login_routes_follow_the_custom_path(): void
    {
        $this->useAdminPath('backend');

        $this->get('/backend/login')->assertOk();
        $this->get('/admin/login')->assertNotFound();

        $this->assertSame(url('/backend/login'), route('login'));
        $this->assertSame(url('/backend/logout'), route('logout'));
    }

    public function test_admin_routes_follow_the_custom_path_and_hide_the_default(): void
    {
        $this->useAdminPath('backend');

        $this->get('/admin')->assertNotFound();
        $this->get('/admin/configuracoes')->assertNotFound();

        $this->assertSame(url('/backend/configuracoes'), route('admin.configuracoes.index'));
        $this->assertSame(url('/backend/aparencia'), route('admin.aparencia.index'));
    }

    public function test_full_login_flow_works_on_the_custom_path(): void
    {
        $admin = User::factory()->admin()->create();
        $this->useAdminPath('backend');

        $this->post('/backend/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get('/backend/configuracoes')->assertOk();
    }

    public function test_stored_setting_wins_over_the_config_default(): void
    {
        $this->app->make(Settings::class)->set(AdminPreferences::KEY_PATH, 'painel-x', 'admin');

        $this->useAdminPath('qualquer'); // config default irrelevante quando há setting

        $this->get('/painel-x/login')->assertOk();
        $this->get('/qualquer/login')->assertNotFound();

        $this->assertSame(url('/painel-x/login'), route('login'));
    }

    public function test_invalid_stored_value_falls_back_to_the_config_default(): void
    {
        $this->app->make(Settings::class)->set(AdminPreferences::KEY_PATH, 'não é válido!', 'admin');

        $this->useAdminPath('qualquer');

        // setting inválido é ignorado → vale o default do config ('qualquer')
        $this->get('/qualquer/login')->assertOk();
        $this->get('/admin/login')->assertNotFound();
    }
}
