<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleLoaderProvider extends ServiceProvider
{
    /**
     * Register every module service provider found under app/Modules.
     *
     * @return list<class-string<ServiceProvider>>
     */
    public function register(): void
    {
        foreach ($this->discoverModuleProviders() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    private function discoverModuleProviders(): array
    {
        $appPath = rtrim(str_replace('\\', '/', app_path()), '/');

        $files = glob($appPath.'/Modules/*/Providers/*ServiceProvider.php') ?: [];
        sort($files);

        $providers = [];

        foreach ($files as $file) {
            $relative = substr($file, strlen($appPath) + 1);
            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

            if (class_exists($class) && is_subclass_of($class, ServiceProvider::class)) {
                $providers[] = $class;
            }
        }

        return $providers;
    }
}
