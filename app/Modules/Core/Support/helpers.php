<?php

use App\Modules\Core\Services\AdminPreferences;
use App\Modules\Core\Services\Module;
use App\Modules\Core\Services\ModuleState;

if (! function_exists('admin_path')) {
    /**
     * Current URL prefix of the admin panel (setting `admin_path`,
     * env/config fallback `cms.admin.default_path`, default "admin").
     *
     * Evaluated when routes are loaded, so it must be cheap and never throw
     * (pre-install the settings table does not exist yet).
     */
    function admin_path(): string
    {
        return app(AdminPreferences::class)->adminPath();
    }
}

if (! function_exists('module_base_dirs')) {
    /**
     * Base directories scanned for modules: bundled code plus the storage
     * path where uploaded modules are extracted.
     *
     * @return list<string>
     */
    function module_base_dirs(): array
    {
        return [
            app_path('Modules'),
            (string) config('cms.modules.storage_path', env('CMS_MODULES_STORAGE_PATH', storage_path('app/modules'))),
        ];
    }
}

if (! function_exists('module_inactive_slugs')) {
    /**
     * Slugs of modules deactivated in the module manager. Read from the
     * file-based state so it works at bootstrap (no database required).
     *
     * @return list<string>
     */
    function module_inactive_slugs(): array
    {
        try {
            return (new ModuleState)->inactive();
        } catch (Throwable) {
            return [];
        }
    }
}

if (! function_exists('module_routes_files')) {
    /**
     * Route files contributed by active modules for the given group
     * ({web|auth|admin|installer}), from bundled and uploaded modules.
     *
     * @return list<string>
     */
    function module_routes_files(string $group): array
    {
        $inactive = module_inactive_slugs();
        $files = [];

        foreach (module_base_dirs() as $base) {
            $match = rtrim(str_replace('\\', '/', $base), '/')."/*/routes/{$group}.php";

            foreach (glob($match) ?: [] as $file) {
                if (in_array(Module::slugFrom(basename(dirname(dirname($file)))), $inactive, true)) {
                    continue;
                }

                $files[] = $file;
            }
        }

        sort($files);

        return $files;
    }
}
