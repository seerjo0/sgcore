<?php

namespace App\Modules\PageBuilder\Services;

use App\Modules\Core\Services\Settings;
use App\Modules\Media\Models\Medium;
use App\Modules\Pages\Models\Page;

/**
 * Per-render context that resolves dynamic bindings (`{site:name}`,
 * `{page:title}`, `{media:12}`, `{year}`) found in component props before the
 * Blade theme renders them. Binding syntax is deliberately tiny: only known
 * keys resolve, unknown tokens are left untouched (then escaped as text).
 */
class RenderContext
{
    public function __construct(
        private Settings $settings,
        public readonly ?Page $page = null,
    ) {}

    /**
     * Resolve every binding in a single prop value.
     */
    public function resolve(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_replace_callback(
            '/\{([a-z_]+)(?::([a-zA-Z0-9_-]+))?\}/i',
            fn (array $m): string => $this->binding($m[1], $m[2] ?? ''),
            $value,
        ) ?? $value;
    }

    /**
     * Resolve bindings recursively inside a props array.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function resolveProps(array $props): array
    {
        foreach ($props as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $nested => $item) {
                    $value[$nested] = $this->resolve($item);
                }
            } else {
                $value = $this->resolve($value);
            }

            $props[$key] = $value;
        }

        return $props;
    }

    private function binding(string $name, string $argument): string
    {
        return match (strtolower($name)) {
            'year' => (string) now()->year,
            'site', 'sitename' => (string) $this->settings->get('site_name', config('app.name')),
            'page', 'pagetitle' => (string) ($this->page?->title ?? ''),
            'pagedescription' => (string) ($this->page?->seo_description ?? ''),
            'media' => $this->mediaUrl($argument),
            default => '{'.$name.($argument !== '' ? ':'.$argument : '').'}',
        };
    }

    private function mediaUrl(string $argument): string
    {
        if ($argument === '') {
            return '';
        }

        if (ctype_digit($argument)) {
            return (string) (Medium::query()->find((int) $argument)?->url() ?? '');
        }

        return '/media/'.ltrim($argument, '/');
    }
}
