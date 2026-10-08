<?php

namespace App\Modules\Themes\Services;

/**
 * Reads and validates theme.json manifests (used by discovery and ZIP import).
 */
class ThemeManifest
{
    public const FILE = 'theme.json';

    /**
     * Slot types supported by the renderer.
     *
     * @var list<string>
     */
    public const SLOT_TYPES = ['text', 'richtext', 'image', 'gallery', 'logo', 'menu'];

    /**
     * Decode and validate the manifest of the theme located at $path.
     *
     * @return array{0: ?array<string, mixed>, 1: ?string} [manifest, error]
     */
    public function load(string $path): array
    {
        $file = rtrim($path, '/\\').'/'.self::FILE;

        if (! is_file($file)) {
            return [null, 'Arquivo theme.json não encontrado.'];
        }

        $raw = file_get_contents($file);

        if ($raw === false || trim($raw) === '') {
            return [null, 'Arquivo theme.json vazio ou ilegível.'];
        }

        // Editors often save JSON with a UTF-8 BOM, which json_decode rejects.
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $manifest = json_decode($raw, true);

        if (! is_array($manifest)) {
            return [null, 'theme.json não contém JSON válido ('.json_last_error_msg().').'];
        }

        $error = $this->validate($manifest, $path);

        return [$manifest, $error];
    }

    /**
     * Structural validation of a decoded manifest against the theme directory.
     * Returns a pt-BR error message or null when valid.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function validate(array $manifest, string $path): ?string
    {
        $name = $manifest['name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            return 'O campo "name" é obrigatório e deve ser uma string não vazia.';
        }

        $templates = $manifest['templates'] ?? null;

        if (! is_array($templates)) {
            return 'O campo "templates" é obrigatório e deve ser um objeto.';
        }

        foreach (['home', 'page'] as $required) {
            $file = $templates[$required] ?? null;

            if (! is_string($file) || $file === '') {
                return "O template \"{$required}\" é obrigatório em \"templates\".";
            }

            if (! is_file(rtrim($path, '/\\').'/'.ltrim($file, '/'))) {
                return "O arquivo de template \"{$file}\" não existe no tema.";
            }
        }

        $fourOhFour = $templates['404'] ?? null;

        if (is_string($fourOhFour) && $fourOhFour !== ''
            && ! is_file(rtrim($path, '/\\').'/'.ltrim($fourOhFour, '/'))) {
            return "O arquivo de template \"{$fourOhFour}\" não existe no tema.";
        }

        $slots = $manifest['slots'] ?? null;

        if ($slots !== null) {
            if (! is_array($slots)) {
                return 'O campo "slots" deve ser um objeto.';
            }

            foreach ($slots as $id => $spec) {
                if (! is_array($spec)) {
                    return "O slot \"{$id}\" deve ser um objeto.";
                }

                $type = $spec['type'] ?? null;

                if (! is_string($type) || ! in_array($type, self::SLOT_TYPES, true)) {
                    return "O slot \"{$id}\" tem tipo inválido (use: ".implode(', ', self::SLOT_TYPES).').';
                }

                $scope = $spec['scope'] ?? null;

                if ($scope !== null && ! in_array($scope, ['global', 'page'], true)) {
                    return "O slot \"{$id}\" tem scope inválido (use: global ou page).";
                }
            }
        }

        $menus = $manifest['menus'] ?? null;

        if ($menus !== null && ! is_array($menus)) {
            return 'O campo "menus" deve ser um objeto.';
        }

        return null;
    }
}
