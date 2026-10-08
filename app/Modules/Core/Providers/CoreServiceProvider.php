<?php

namespace App\Modules\Core\Providers;

use App\Models\User;
use App\Modules\Auth\Enums\Role;
use App\Modules\Core\Console\InstallCommand;
use App\Modules\Core\Console\UninstallCommand;
use App\Modules\Core\InstallSteps\DefaultSettingsStep;
use App\Modules\Core\Services\DatabaseSetup;
use App\Modules\Core\Services\EnvironmentWriter;
use App\Modules\Core\Services\InstallerLock;
use App\Modules\Core\Services\InstallerService;
use App\Modules\Core\Services\InstallStepRegistry;
use App\Modules\Core\Services\Settings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cms.php', 'cms');

        $this->app->singleton(Settings::class);
        $this->app->singleton(InstallerLock::class);
        $this->app->singleton(InstallStepRegistry::class);
        $this->app->singleton(EnvironmentWriter::class);
        $this->app->singleton(DatabaseSetup::class);
        $this->app->singleton(InstallerService::class);

        $this->app->tag(DefaultSettingsStep::class, 'cms.install_steps');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'core');

        Gate::define('manage-settings', fn (User $user): bool => $user->role === Role::Admin);

        $this->commands([
            InstallCommand::class,
            UninstallCommand::class,
        ]);
    }
}
