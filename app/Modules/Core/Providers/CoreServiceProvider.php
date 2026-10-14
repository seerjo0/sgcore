<?php

namespace App\Modules\Core\Providers;

use App\Models\User;
use App\Modules\Auth\Enums\Role;
use App\Modules\Core\Console\CheckUpdatesCommand;
use App\Modules\Core\Console\InstallCommand;
use App\Modules\Core\Console\ServeCommand;
use App\Modules\Core\Console\UninstallCommand;
use App\Modules\Core\InstallSteps\DefaultSettingsStep;
use App\Modules\Core\Services\DatabaseSetup;
use App\Modules\Core\Services\DevServerManager;
use App\Modules\Core\Services\EnvironmentWriter;
use App\Modules\Core\Services\InstallerLock;
use App\Modules\Core\Services\InstallerService;
use App\Modules\Core\Services\InstallStepRegistry;
use App\Modules\Core\Services\ModuleDiscovery;
use App\Modules\Core\Services\ModuleImporter;
use App\Modules\Core\Services\ModuleManifest;
use App\Modules\Core\Services\ModuleState;
use App\Modules\Core\Services\Settings;
use App\Modules\Core\Services\UpdateChecker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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
        $this->app->singleton(DevServerManager::class, fn (): DevServerManager => new DevServerManager);
        $this->app->singleton(ModuleManifest::class);
        $this->app->singleton(ModuleState::class);
        $this->app->singleton(ModuleDiscovery::class);
        $this->app->singleton(ModuleImporter::class);

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
        Gate::define('manage-modules', fn (User $user): bool => $user->role === Role::Admin);

        $this->commands([
            InstallCommand::class,
            UninstallCommand::class,
            ServeCommand::class,
            CheckUpdatesCommand::class,
        ]);

        // Site-wide "update available" banner on the admin shell. This only
        // reads the cached release (no network on every page); the dashboard,
        // the "check now" action and the scheduled command refresh it.
        View::composer('core::admin.layout', function (\Illuminate\View\View $view): void {
            $updater = app(UpdateChecker::class);

            $view->with([
                'update' => $updater->updateAvailable(mayFetch: false),
                'updateCurrent' => $updater->currentVersion(),
            ]);
        });
    }
}
