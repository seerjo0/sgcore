<?php

namespace App\Modules\Media\Providers;

use App\Models\User;
use App\Modules\Auth\Enums\Role;
use App\Modules\Media\Services\MediaStorage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MediaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(MediaStorage::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'media');

        Gate::define('manage-media', fn (User $user): bool => in_array($user->role, [Role::Admin, Role::Editor], true));
    }
}
