<?php

namespace App\Modules\Themes\Providers;

use App\Models\User;
use App\Modules\Auth\Enums\Role;
use App\Modules\Themes\Services\ContentStore;
use App\Modules\Themes\Services\ThemeDiscovery;
use App\Modules\Themes\Services\ThemeImporter;
use App\Modules\Themes\Services\ThemeManifest;
use App\Modules\Themes\Services\ThemeRenderer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ThemesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ThemeManifest::class);
        $this->app->singleton(ThemeDiscovery::class);
        $this->app->singleton(ContentStore::class);
        $this->app->singleton(ThemeRenderer::class);
        $this->app->singleton(ThemeImporter::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'themes');

        Gate::define('manage-appearance', fn (User $user): bool => $user->role === Role::Admin);
    }
}
