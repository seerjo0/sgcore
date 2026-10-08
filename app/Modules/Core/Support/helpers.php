<?php

use App\Modules\Core\Services\AdminPreferences;

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
