<?php

namespace App\Modules\Blog\Providers;

use App\Models\User;
use App\Modules\Auth\Enums\Role;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'blog');

        Gate::define('manage-blog', fn (User $user): bool => in_array($user->role, [Role::Admin, Role::Editor], true));
    }
}
