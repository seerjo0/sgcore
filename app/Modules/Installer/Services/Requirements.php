<?php

namespace App\Modules\Installer\Services;

use Illuminate\Support\Facades\File;

class Requirements
{
    /**
     * Run every pre-installation check.
     *
     * @return array{checks: array<int, array{label: string, ok: bool, detail: ?string}>, ready: bool}
     */
    public function evaluate(): array
    {
        $checks = [
            $this->check(
                __('installer.req_php'),
                version_compare(PHP_VERSION, '8.3.0', '>='),
                __('installer.req_php_version', ['version' => PHP_VERSION])
            ),
            $this->check(
                __('installer.req_pdo_mysql'),
                extension_loaded('pdo_mysql'),
            ),
            $this->check(
                __('installer.req_mbstring'),
                extension_loaded('mbstring'),
            ),
            $this->check(
                __('installer.req_openssl'),
                extension_loaded('openssl'),
            ),
            $this->check(
                __('installer.req_curl'),
                extension_loaded('curl'),
            ),
            $this->check(
                __('installer.req_dom'),
                extension_loaded('dom'),
            ),
            $this->check(
                __('installer.req_fileinfo'),
                extension_loaded('fileinfo'),
            ),
            $this->check(
                __('installer.req_zip'),
                extension_loaded('zip'),
            ),
            $this->check(
                __('installer.req_gd'),
                extension_loaded('gd'),
            ),
            $this->check(
                __('installer.req_storage'),
                File::isDirectory(storage_path()) && File::isWritable(storage_path()),
            ),
            $this->check(
                __('installer.req_bootstrap_cache'),
                File::isDirectory(base_path('bootstrap/cache')) && File::isWritable(base_path('bootstrap/cache')),
            ),
        ];

        $env = $this->ensureEnvironmentFile();
        $checks[] = $env;

        return [
            'checks' => $checks,
            'ready' => collect($checks)->every(fn (array $check): bool => $check['ok']),
        ];
    }

    /**
     * Create .env from the template when missing, then report writability.
     *
     * @return array{label: string, ok: bool, detail: ?string}
     */
    private function ensureEnvironmentFile(): array
    {
        $path = (string) config('cms.installer.env_file');
        $template = (string) config('cms.installer.env_template');

        if (! File::exists($path)) {
            if (! File::exists($template)) {
                return $this->check(__('installer.req_env'), false, __('installer.req_env_template_missing', ['path' => $path]));
            }

            File::copy($template, $path);
        }

        if (! File::exists($path)) {
            return $this->check(
                __('installer.req_env'),
                false,
                __('installer.req_env_create_fail', ['path' => $path])
            );
        }

        return $this->check(__('installer.req_env'), File::isWritable($path), $path);
    }

    /**
     * @return array{label: string, ok: bool, detail: ?string}
     */
    private function check(string $label, bool $ok, ?string $detail = null): array
    {
        return ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    }
}
