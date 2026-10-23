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

    /*
    |--------------------------------------------------------------------------
    | Site languages (Configurações > Idioma)
    |--------------------------------------------------------------------------
    |
    | Locales offered in the admin settings. Native names are shown as-is
    | (they are the fallback for a user who does not read the current UI
    | language). The stored `site_locale` setting wins over APP_LOCALE.
    |
    */

    'locales' => [
        'pt_BR' => 'Português (Brasil)',
        'en' => 'English',
    ],

    /*
    |--------------------------------------------------------------------------
    | Module manager (admin Módulos)
    |--------------------------------------------------------------------------
    |
    | `state_file` is a JSON file listing which modules are inactive and which
    | uploads are installed. The loader (ModuleLoaderProvider, routes and
    | migrations) reads it at bootstrap without touching the database — the
    | same file-based philosophy as the installer lock. `protected` slugs can
    | never be deactivated (Core/Auth/Installer keep the CMS bootable).
    |
    */

    'modules' => [
        'state_file' => env('CMS_MODULES_STATE_FILE', storage_path('app/modules.php')),
        'storage_path' => env('CMS_MODULES_STORAGE_PATH', storage_path('app/modules')),
        'max_upload_bytes' => env('CMS_MODULES_MAX_UPLOAD_BYTES', 5 * 1024 * 1024),
        'protected' => ['core', 'installer', 'auth'],
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Versão do sgcore
    |--------------------------------------------------------------------------
    |
    | Fonte única da versão em execução, comparada com o último Release do
    | GitHub para o aviso de atualização. Sobrescreva por ambiente com
    | CMS_VERSION (deixe bater com a tag do release, ex.: 1.2.0). Uma revisão
    | reempacotada da mesma versão usa o sufixo -rN (ex.: 1.1.0-r1).
    |
    */

    'version' => env('CMS_VERSION', '1.2.1'),

    /*
    |--------------------------------------------------------------------------
    | Verificador de atualizações (somente leitura)
    |--------------------------------------------------------------------------
    |
    | Consulta os Releases do GitHub e informa ao admin quando existe versão
    | nova. Por padrão é somente leitura: exibe o aviso mas não aplica nada.
    | "source" aceita releases|tags|auto (auto = tenta Release e, se não houver,
    | usa a maior tag Git). A aplicação ("one click") só é possível quando
    | apply.enabled=true E o app é um clone Git com árvore limpa; desligado por
    | padrão por segurança. O resultado fica em cache para não estourar o
    | rate-limit da API anônima do GitHub.
    |
    */

    'updater' => [
        'enabled' => env('CMS_UPDATER_ENABLED', true),
        'repository' => env('CMS_UPDATER_REPOSITORY', 'seerjo0/sgcore'),
        'source' => env('CMS_UPDATER_SOURCE', 'auto'),
        'cache_ttl' => (int) env('CMS_UPDATER_CACHE_TTL', 6 * 3600),
        'timeout' => (int) env('CMS_UPDATER_TIMEOUT', 4),

        'apply' => [
            // Git one-click (VPS com git+composer no PATH). Desligado por padrão.
            'enabled' => env('CMS_UPDATER_ALLOW_APPLY', false),
            'git' => env('CMS_UPDATER_GIT', 'git'),
            'composer' => env('CMS_UPDATER_COMPOSER', 'composer'),
            'remote' => env('CMS_UPDATER_REMOTE', 'origin'),

            // Pacote WordPress-style: extrai um .zip sobre a instalação e roda
            // migrate --force. Não precisa de git/composer/node no servidor.
            'package' => env('CMS_UPDATER_ALLOW_PACKAGE', true),
            'max_package_bytes' => (int) env('CMS_UPDATER_MAX_PACKAGE_BYTES', 200 * 1024 * 1024),

            // Baixar o asset .zip da Release e aplicar (exige allow_package).
            'download' => env('CMS_UPDATER_ALLOW_DOWNLOAD', true),
            'download_timeout' => (int) env('CMS_UPDATER_DOWNLOAD_TIMEOUT', 120),
        ],

        // Atualização automática agendada (sgcore:auto-update). Exige opt-in
        // duplo: além de CMS_UPDATER_ALLOW_APPLY=true, precisa de
        // CMS_UPDATER_AUTO=true. Só faz sentido em instalações via Git com
        // git/composer no PATH — instalações via .zip não suportam auto-update.
        'auto' => [
            'enabled' => env('CMS_UPDATER_AUTO', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Empacotador (sgcore:package)
    |--------------------------------------------------------------------------
    |
    | Monta o zip "public_html" (estilo WordPress): app achatado na raiz com
    | vendor/ e assets já compilados, entry point próprio e um .htaccess/web.config
    | que bloqueiam as pastas internas. O arquivo de release fica em out_dir.
    |
    */

    'packager' => [
        'name' => 'sgcore',
        'output_dir' => env('CMS_PACKAGE_OUTPUT_DIR', base_path('dist')),
        // Caminhos relativos à raiz protegidos no pacote (não vão no zip / não são
        // sobrescritos na atualização). storage/bootstrap/cache mantêm dados do host.
        'protected' => [
            '.env',
            '.env.local',
            'storage',
            'bootstrap/cache',
        ],
    ],

];
