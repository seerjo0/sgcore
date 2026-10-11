<?php

namespace App\Modules\PageBuilder\Services;

use App\Modules\Core\Services\Settings;

/**
 * Global Design System: a validated set of design tokens (colors, typography,
 * spacing scale and breakpoints) persisted in the site settings and exposed in
 * two directions — as CSS variables + utility classes on the public render and
 * as structured tokens for the editor (style manager options).
 */
class DesignSystem
{
    public function __construct(private Settings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function tokens(): array
    {
        $stored = $this->settings->get('design_system');

        if (! is_array($stored)) {
            return $this->defaults();
        }

        return array_replace_recursive($this->defaults(), $stored);
    }

    public function css(): string
    {
        $tokens = $this->tokens();

        $vars = ':root{';
        $vars .= '--pb-primary:'.$this->color($tokens, 'primary').';';
        $vars .= '--pb-secondary:'.$this->color($tokens, 'secondary').';';
        $vars .= '--pb-success:'.$this->color($tokens, 'success').';';
        $vars .= '--pb-danger:'.$this->color($tokens, 'danger').';';
        $vars .= '--pb-warning:'.$this->color($tokens, 'warning').';';
        $vars .= '--pb-info:'.$this->color($tokens, 'info').';';
        $vars .= '--pb-dark:'.$this->color($tokens, 'dark').';';
        $vars .= '--pb-light:'.$this->color($tokens, 'light').';';
        $vars .= '--pb-font-body:'.$this->font($tokens, 'body').';';
        $vars .= '--pb-font-heading:'.$this->font($tokens, 'heading').';';
        $vars .= '}';

        $classes = '';

        foreach (['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark', 'light'] as $name) {
            $classes .= '.pb-bg-'.$name.'{background-color:var(--pb-'.$name.')}';
            $classes .= '.pb-text-'.$name.'{color:var(--pb-'.$name.')}';
        }

        $classes .= '.pb-font-heading{font-family:var(--pb-font-heading)}'
            .'.pb-font-body{font-family:var(--pb-font-body)}';

        // Spacing scale as CSS variables + padding/gap utilities.
        foreach ((array) ($tokens['spacing'] ?? []) as $name => $value) {
            $key = $this->safeKey((string) $name);
            $value = $this->length((string) $value, $this->defaults()['spacing'][$name] ?? '1rem');
            $classes .= ':root{--pb-space-'.$key.':'.$value.'}';
            $classes .= '.pb-pad-'.$key.'{padding:var(--pb-space-'.$key.')}';
            $classes .= '.pb-gap-'.$key.'{gap:var(--pb-space-'.$key.')}';
        }

        // Breakpoints as custom properties (the editor consumes them for the
        // responsive device presets; informational on the public render).
        $bpVars = ':root{';
        $bpAny = false;
        foreach ((array) ($tokens['breakpoints'] ?? []) as $name => $value) {
            $key = $this->safeKey((string) $name);
            $value = $this->length((string) $value, $this->defaults()['breakpoints'][$name] ?? '768px');
            $bpVars .= '--pb-bp-'.$key.':'.$value.';';
            $bpAny = true;
        }
        if ($bpAny) {
            $classes .= $bpVars.'}';
        }

        return $vars.$classes;
    }

    /**
     * Editor-facing tokens (used to feed the style manager color options).
     *
     * @return array<string, mixed>
     */
    public function editorTokens(): array
    {
        $tokens = $this->tokens();

        return [
            'colors' => array_values(array_map(fn (string $color): string => (string) ($color !== '' ? $color : '#000000'), $tokens['colors'])),
            'fonts' => [
                'body' => $this->font($tokens, 'body'),
                'heading' => $this->font($tokens, 'heading'),
            ],
            'spacing' => array_map(
                fn (string $value, string $name): array => ['name' => $name, 'value' => $value],
                array_map(strval(...), (array) ($tokens['spacing'] ?? [])),
                array_keys((array) ($tokens['spacing'] ?? [])),
            ),
            'breakpoints' => array_map(
                fn (string $value, string $name): array => ['name' => $name, 'value' => $value],
                array_map(strval(...), (array) ($tokens['breakpoints'] ?? [])),
                array_keys((array) ($tokens['breakpoints'] ?? [])),
            ),
        ];
    }

    /**
     * Persist a validated token set; returns validation errors (if any).
     *
     * @param  array<string, mixed>  $tokens
     * @return array<string, mixed>
     */
    public function save(array $tokens): array
    {
        $defaults = $this->defaults();
        $colors = is_array($tokens['colors'] ?? null) ? $tokens['colors'] : $defaults['colors'];
        $valid = [];

        foreach ($defaults['colors'] as $name => $color) {
            $candidate = $colors[$name] ?? $color;
            $valid[$name] = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $candidate) === 1
                ? $candidate
                : $color;
        }

        $fonts = is_array($tokens['fonts'] ?? null) ? $tokens['fonts'] : [];
        $spacing = is_array($tokens['spacing'] ?? null) ? $tokens['spacing'] : [];
        $breakpoints = is_array($tokens['breakpoints'] ?? null) ? $tokens['breakpoints'] : [];

        $cleanScale = function (array $input, array $defaults): array {
            $out = [];
            foreach ($defaults as $name => $fallback) {
                $out[$name] = $this->length((string) ($input[$name] ?? $fallback), (string) $fallback);
            }

            return $out;
        };

        $this->settings->set('design_system', [
            'colors' => $valid,
            'fonts' => [
                'body' => $this->cssFont((string) ($fonts['body'] ?? $defaults['fonts']['body'])),
                'heading' => $this->cssFont((string) ($fonts['heading'] ?? $defaults['fonts']['heading'])),
            ],
            'spacing' => $cleanScale($spacing, $defaults['spacing']),
            'breakpoints' => $cleanScale($breakpoints, $defaults['breakpoints']),
        ]);

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'colors' => [
                'primary' => '#4f46e5',
                'secondary' => '#64748b',
                'success' => '#059669',
                'danger' => '#dc2626',
                'warning' => '#f59e0b',
                'info' => '#0ea5e9',
                'dark' => '#0f172a',
                'light' => '#f8fafc',
            ],
            'fonts' => [
                'body' => "system-ui, -apple-system, 'Segoe UI', sans-serif",
                'heading' => "system-ui, -apple-system, 'Segoe UI', sans-serif",
            ],
            'spacing' => ['xs' => '0.5rem', 'sm' => '1rem', 'md' => '2rem', 'lg' => '4rem'],
            'breakpoints' => ['sm' => '576px', 'md' => '768px', 'lg' => '992px', 'xl' => '1200px'],
        ];
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    private function color(array $tokens, string $name): string
    {
        $value = $tokens['colors'][$name] ?? null;

        return is_string($value) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1 ? $value : '#000000';
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    private function font(array $tokens, string $name): string
    {
        $value = $tokens['fonts'][$name] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : 'sans-serif';
    }

    private function cssFont(string $value): string
    {
        $value = preg_replace('/[;"<>{}]/', '', trim($value)) ?? '';

        return $value !== '' ? $value : 'sans-serif';
    }

    /**
     * Keep a CSS length token (digits + unit); fall back when it is malformed.
     */
    private function length(string $value, string $fallback): string
    {
        $value = trim($value);

        return preg_match('/^\d+(\.\d+)?(px|rem|em|%)$/', $value) === 1 ? $value : $fallback;
    }

    /**
     * Restrict a token key (spacing/breakpoint name) to a safe CSS identifier.
     */
    private function safeKey(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $name) ?? '';
    }
}
