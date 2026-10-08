<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class EnvironmentWriter
{
    /**
     * Keys the installer owns; everything else in the file is preserved.
     *
     * @var array<int, string>
     */
    private const MANAGED_KEYS = [
        'APP_NAME',
        'APP_KEY',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
    ];

    /**
     * Write (or merge) values into the environment file and return its contents.
     *
     * @param  array<string, string>  $values
     */
    public function write(array $values): string
    {
        $path = (string) config('cms.installer.env_file');
        $template = (string) config('cms.installer.env_template');

        $source = File::exists($path)
            ? File::get($path)
            : (File::exists($template) ? File::get($template) : '');

        $values = $this->sanitize($values);
        $values['APP_KEY'] = $this->resolveAppKey($values, $source);

        $contents = $this->merge($source, $values);

        File::ensureDirectoryExists(dirname($path));

        if (File::put($path, $contents, true) === false) {
            throw new RuntimeException(
                "Não foi possível gravar o arquivo {$path}. Verifique as permissões de escrita."
            );
        }

        return $contents;
    }

    /**
     * Read the current value of a key from the environment file.
     */
    public function read(string $key): ?string
    {
        $path = (string) config('cms.installer.env_file');

        if (! File::exists($path)) {
            return null;
        }

        return $this->existingValue(File::get($path), $key);
    }

    /**
     * Provided value wins; otherwise keep the existing key; otherwise generate one.
     *
     * @param  array<string, string>  $values
     */
    private function resolveAppKey(array $values, string $source): string
    {
        $key = $values['APP_KEY'] ?? '';

        if ($key === '') {
            $key = $this->existingValue($source, 'APP_KEY');
        }

        if ($key === '') {
            $key = 'base64:'.base64_encode(random_bytes(32));
        }

        return $key;
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function sanitize(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            $clean[$key] = str_replace(["\r", "\n"], '', (string) $value);
        }

        return $clean;
    }

    /**
     * Merge managed keys into the file, preserving every other line.
     *
     * @param  array<string, string>  $values
     */
    private function merge(string $source, array $values): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $source) ?: [];
        $output = [];
        $written = [];

        foreach ($lines as $line) {
            $key = $this->keyOf($line);

            if ($key !== null && in_array($key, self::MANAGED_KEYS, true)) {
                if (array_key_exists($key, $values)) {
                    if (! isset($written[$key])) {
                        $output[] = $key.'='.$this->format($values[$key]);
                        $written[$key] = true;
                    }
                } else {
                    $output[] = $line;
                }

                continue;
            }

            $output[] = $line;
        }

        foreach ($values as $key => $value) {
            if (in_array($key, self::MANAGED_KEYS, true) && ! isset($written[$key])) {
                $output[] = $key.'='.$this->format($value);
                $written[$key] = true;
            }
        }

        return rtrim(implode("\n", $output), "\n")."\n";
    }

    private function keyOf(string $line): ?string
    {
        if (preg_match('/^\s*#?\s*([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function existingValue(string $contents, string $key): string
    {
        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            $trimmed = ltrim($line);

            if (str_starts_with($trimmed, '#') || $this->keyOf($line) !== $key) {
                continue;
            }

            if (preg_match('/^\s*[A-Za-z_][A-Za-z0-9_]*\s*=\s*(.*)$/', $line, $matches)) {
                return trim($matches[1], " \t\"'");
            }
        }

        return '';
    }

    private function format(string $value): string
    {
        if ($value !== '' && preg_match('/[\s"\'#$\\\\]/', $value)) {
            return '"'.addcslashes($value, '"\\').'"';
        }

        return $value;
    }
}
