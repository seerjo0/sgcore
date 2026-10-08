<?php

namespace App\Modules\Pages\Providers;

use App\Models\User;
use App\Modules\Auth\Enums\Role;
use App\Modules\Pages\InstallSteps\HomePageStep;
use App\Modules\Pages\InstallSteps\SampleContentStep;
use App\Modules\Pages\Services\ContentSanitizer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PagesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ContentSanitizer::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pages');

        Gate::define('manage-pages', fn (User $user): bool => in_array($user->role, [Role::Admin, Role::Editor], true));
        Gate::define('manage-menus', fn (User $user): bool => in_array($user->role, [Role::Admin, Role::Editor], true));

        $this->app->tag([HomePageStep::class, SampleContentStep::class], 'cms.install_steps');
    }
}
