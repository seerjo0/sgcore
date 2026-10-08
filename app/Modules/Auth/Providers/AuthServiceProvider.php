<?php

namespace App\Modules\Auth\Providers;

use App\Models\User;
use App\Modules\Auth\Http\Middleware\EnsureActiveUser;
use App\Modules\Auth\Http\Middleware\EnsureRole;
use App\Modules\Auth\InstallSteps\CreateAdminUserStep;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->tag(CreateAdminUserStep::class, 'cms.install_steps');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'auth');

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('role', EnsureRole::class);
        $router->aliasMiddleware('active', EnsureActiveUser::class);

        Gate::before(fn (User $user): ?bool => $user->isAdmin() ? true : null);
        Gate::define('manage-users', fn (User $user): bool => $user->isAdmin());
    }
}
