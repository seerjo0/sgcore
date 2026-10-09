<?php

namespace App\Modules\PageBuilder\Providers;

use App\Modules\PageBuilder\Services\BuilderSanitizer;
use App\Modules\PageBuilder\Services\Renderer;
use Illuminate\Support\ServiceProvider;

class PageBuilderServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pagebuilder.php', 'pagebuilder');

        $this->app->singleton(BuilderSanitizer::class);
        $this->app->singleton(Renderer::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pagebuilder');
    }
}
