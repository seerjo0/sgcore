<?php

namespace App\Modules\PageBuilder\Providers;

use App\Modules\PageBuilder\Services\BuilderSanitizer;
use App\Modules\PageBuilder\Services\ComponentRegistry;
use App\Modules\PageBuilder\Services\Components\Components;
use App\Modules\PageBuilder\Services\DesignSystem;
use App\Modules\PageBuilder\Services\ProjectExtractor;
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
        $this->app->singleton(DesignSystem::class);
        $this->app->singleton(ProjectExtractor::class);

        $this->app->singleton(ComponentRegistry::class, function ($app): ComponentRegistry {
            $registry = new ComponentRegistry($app->make(ProjectExtractor::class));

            foreach (Components::all($app->make(BuilderSanitizer::class)) as $component) {
                $registry->register($component);
            }

            return $registry;
        });
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
