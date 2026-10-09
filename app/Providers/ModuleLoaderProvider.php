<?php

namespace App\Providers;

use App\Modules\Core\Services\Module;
use App\Modules\Core\Services\ModuleState;
use Illuminate\Support\ServiceProvider;

class ModuleLoaderProvider extends ServiceProvider
{
    /**
     * Register every module service provider found under app/Modules and the
     * module storage path, skipping modules explicitly deactivated in the
     * module manager (cms.modules.state_file).
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
        $inactive = $this->inactiveSlugs();
        $providers = [];

        foreach ($this->moduleBaseDirs() as $base) {
            $appPath = rtrim(str_replace('\\', '/', $base), '/');

            $files = glob($appPath.'/*/Providers/*ServiceProvider.php') ?: [];
            sort($files);

            foreach ($files as $file) {
                $slug = Module::slugFrom(basename(dirname(dirname($file))));

                if (in_array($slug, $inactive, true)) {
                    continue;
                }

                $relative = substr($file, strlen($appPath) + 1);
                $class = 'App\\Modules\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

                if (class_exists($class) && is_subclass_of($class, ServiceProvider::class)) {
                    $providers[] = $class;
                }
            }
        }

        return $providers;
    }

    /**
     * @return list<string>
     */
    private function inactiveSlugs(): array
    {
        try {
            return (new ModuleState)->inactive();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function moduleBaseDirs(): array
    {
        return [
            app_path('Modules'),
            (string) config(
                'cms.modules.storage_path',
                env('CMS_MODULES_STORAGE_PATH', storage_path('app/modules')),
            ),
        ];
    }
}
