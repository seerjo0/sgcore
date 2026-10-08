<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installer
    |--------------------------------------------------------------------------
    |
    | Paths and limits used by the web installer and the cms:install command.
    | Every value can be overridden per environment for testing purposes.
    |
    */

    'installer' => [
        'lock_file' => env('CMS_LOCK_FILE', storage_path('app/installed.lock')),
        'env_file' => env('CMS_ENV_FILE', base_path('.env')),
        'env_template' => base_path('.env.example'),
        'create_storage_link' => env('CMS_CREATE_STORAGE_LINK', true),
        'max_theme_upload_bytes' => env('CMS_MAX_THEME_UPLOAD_BYTES', 10 * 1024 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | Upload limits and image processing settings used by the media library.
    |
    */

    'media' => [
        'max_upload_bytes' => env('CMS_MEDIA_MAX_UPLOAD_BYTES', 10 * 1024 * 1024),
        'max_dimension' => env('CMS_MEDIA_MAX_DIMENSION', 2000),
        'quality' => env('CMS_MEDIA_QUALITY', 85),
    ],

    /*
    |--------------------------------------------------------------------------
    | Themes
    |--------------------------------------------------------------------------
    |
    | Where themes live: bundled themes ship in resources/themes and imported
    | ZIP themes are extracted into storage/app/themes.
    |
    */

    'themes' => [
        'builtin_path' => env('CMS_THEMES_BUILTIN_PATH', resource_path('themes')),
        'storage_path' => env('CMS_THEMES_STORAGE_PATH', storage_path('app/themes')),
        'default_theme' => env('CMS_DEFAULT_THEME', 'classic'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin appearance
    |--------------------------------------------------------------------------
    |
    | Display modes and accent colors offered in Configurações > Admin.
    | The view and the validation rules read these lists, so adding an
    | entry here extends the UI and the accepted values automatically.
    |
    */

    'admin' => [
        'default_mode' => 'claro',
        'default_color' => 'azul',
        // Default URL prefix of the admin panel (env ADMIN_PATH). The setting
        // `admin_path` saved in Configurações overrides this at runtime.
        'default_path' => env('ADMIN_PATH', 'admin'),
        'modes' => [
            'claro' => 'Claro',
            'escuro' => 'Escuro',
        ],
        'colors' => [
            'azul' => ['label' => 'Azul', 'hex' => '#4f46e5'],
            'amarelo' => ['label' => 'Amarelo', 'hex' => '#ca8a04'],
            'verde' => ['label' => 'Verde', 'hex' => '#059669'],
            'roxo' => ['label' => 'Roxo', 'hex' => '#7c3aed'],
            'vermelho' => ['label' => 'Vermelho', 'hex' => '#dc2626'],
            'rosa' => ['label' => 'Rosa', 'hex' => '#db2777'],
        ],
    ],

];
