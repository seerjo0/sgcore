<?php

namespace App\Modules\Core\Services;

/**
 * Reads and validates module.json manifests (used by discovery and ZIP import).
 */
class ModuleManifest
{
    public const FILE = 'module.json';

    /**
     * Regular expression for a module slug: valid PHP-ish identifier segment,
     * lowercase, so `App\Modules\{slug}` works as a PSR-4 namespace.
     */
    public const SLUG_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /**
     * Decode and validate the manifest of the module located at $path.
     *
     * @return array{0: ?array<string, mixed>, 1: ?string} [manifest, error]
     */
    public function load(string $path): array
    {
        $file = rtrim($path, '/\\').'/'.self::FILE;

        if (! is_file($file)) {
            return [null, 'Arquivo module.json não encontrado.'];
        }

        $raw = file_get_contents($file);

        if ($raw === false || trim($raw) === '') {
            return [null, 'Arquivo module.json vazio ou ilegível.'];
        }

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $manifest = json_decode($raw, true);

        if (! is_array($manifest)) {
            return [null, 'module.json não contém JSON válido ('.json_last_error_msg().').'];
        }

        return [$manifest, $this->validate($manifest)];
    }

    /**
     * Structural validation of a decoded module.json manifest.
     * Returns a pt-BR error message or null when valid.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function validate(array $manifest): ?string
    {
        $name = $manifest['name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            return 'O campo "name" é obrigatório e deve ser uma string não vazia.';
        }

        $slug = $manifest['slug'] ?? null;

        if ($slug !== null && (preg_match(self::SLUG_PATTERN, (string) $slug) !== 1)) {
            return 'O campo "slug" deve usar letras minúsculas, números e "_" (sem hífen ou espaço).';
        }

        foreach (['description', 'version', 'author'] as $field) {
            if (isset($manifest[$field]) && ! is_string($manifest[$field])) {
                return "O campo \"{$field}\" deve ser uma string.";
            }
        }

        $requires = $manifest['requires'] ?? null;

        if ($requires !== null && ! is_array($requires)) {
            return 'O campo "requires" deve ser uma lista de módulos.';
        }

        if ($requires !== null) {
            foreach ($requires as $dependency) {
                if (! is_string($dependency) || preg_match(self::SLUG_PATTERN, $dependency) !== 1) {
                    return 'O campo "requires" deve conter apenas slugs válidos de módulos.';
                }
            }
        }

        return null;
    }
}
