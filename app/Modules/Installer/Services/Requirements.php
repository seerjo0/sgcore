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
            $this->check('PHP 8.3 ou superior', version_compare(PHP_VERSION, '8.3.0', '>='), 'Versão atual: '.PHP_VERSION),
            $this->check('Extensão pdo_mysql', extension_loaded('pdo_mysql')),
            $this->check('Extensão mbstring', extension_loaded('mbstring')),
            $this->check('Extensão openssl', extension_loaded('openssl')),
            $this->check('Extensão cURL', extension_loaded('curl')),
            $this->check('Extensão dom (XML)', extension_loaded('dom')),
            $this->check('Extensão fileinfo', extension_loaded('fileinfo')),
            $this->check('Extensão zip', extension_loaded('zip')),
            $this->check('Extensão gd (imagens)', extension_loaded('gd')),
            $this->check('Pasta storage/ gravável', File::isDirectory(storage_path()) && File::isWritable(storage_path())),
            $this->check('Pasta bootstrap/cache gravável', File::isDirectory(base_path('bootstrap/cache')) && File::isWritable(base_path('bootstrap/cache'))),
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
                return $this->check('Arquivo .env', false, 'Template .env.example não encontrado em '.$path);
            }

            File::copy($template, $path);
        }

        if (! File::exists($path)) {
            return $this->check(
                'Arquivo .env',
                false,
                'Não foi possível criar o arquivo em '.$path.' — crie manualmente a partir do .env.example.'
            );
        }

        return $this->check('Arquivo .env', File::isWritable($path), $path);
    }

    /**
     * @return array{label: string, ok: bool, detail: ?string}
     */
    private function check(string $label, bool $ok, ?string $detail = null): array
    {
        return ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    }
}
