<?php

namespace App\Modules\PageBuilder\Services;

use App\Modules\PageBuilder\Services\Components\ComponentNode;

/**
 * Extracts a structured component tree from a GrapesJS project payload.
 *
 * A node is renderable only when its root element carries `data-pb-type` (the
 * registry allowlist); anything else is dropped together with its subtree. The
 * extracted output is therefore safe by construction: every node that reached
 * the public tree was, at some point, validated against the registry.
 *
 * Returns null when the project contains no typed components — the caller then
 * falls back to the legacy sanitized-HTML pipeline, keeping old pages working.
 */
class ProjectExtractor
{
    public function __construct(private BuilderSanitizer $sanitizer) {}

    /**
     * @param  array<string, mixed>|null  $project
     * @return list<ComponentNode>|null
     */
    public function extract(?array $project): ?array
    {
        $components = is_array($project['components'] ?? null) ? $project['components'] : [];

        $counter = 0;
        $tree = $this->extractNodes($components, $counter);

        return $tree === [] ? null : $tree;
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @return list<ComponentNode>
     */
    private function extractNodes(array $nodes, int &$counter): array
    {
        $out = [];

        foreach ($nodes as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $attributes = is_array($raw['attributes'] ?? null) ? $raw['attributes'] : [];
            $type = $attributes['data-pb-type'] ?? null;

            if (! is_string($type) || trim($type) === '') {
                continue;
            }

            $children = is_array($raw['components'] ?? null) ? $raw['components'] : [];

            $out[] = new ComponentNode(
                type: trim($type),
                id: $this->nodeId($attributes['id'] ?? null, trim($type), $counter),
                classes: $this->classes($raw['classes'] ?? []),
                style: $this->sanitizer->sanitizeCss((string) ($attributes['style'] ?? '')),
                props: $this->props($attributes, $raw),
                children: $this->extractNodes($children, $counter),
            );
        }

        return $out;
    }

    /**
     * Props = every `data-pb-*` attribute (the trait-edited surface) plus the
     * native attributes the editor already carries (src/alt/href/…) and the
     * rich `content`, derived from the node's own text or its text children.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function props(array $attributes, array $raw): array
    {
        $props = [];

        foreach ($attributes as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'data-pb-') || $key === 'data-pb-type') {
                continue;
            }

            $props[substr($key, strlen('data-pb-'))] = $value;
        }

        foreach (['src', 'alt', 'title', 'href', 'width', 'height', 'type', 'name', 'value', 'placeholder'] as $native) {
            if (isset($attributes[$native]) && ! array_key_exists($native, $props)) {
                $props[$native] = $attributes[$native];
            }
        }

        $content = is_string($raw['content'] ?? null) ? trim($raw['content']) : '';

        if ($content === '' && is_array($raw['components'] ?? null)) {
            $content = $this->textOf($raw['components']);
        }

        $props['content'] = $content;

        // Heading level comes from the tag when the editor did not set it.
        if (! isset($props['level']) && preg_match('/^h([1-6])$/i', (string) ($raw['tagName'] ?? ''), $m) === 1) {
            $props['level'] = $m[1];
        }

        // List items fall back to one line of content per child item.
        if (($props['items'] ?? '') === '' && $content !== '') {
            $props['items'] = $content;
        }

        return $props;
    }

    /**
     * Concatenate the text content of a subtree (GrapesJS stores inline text in
     * nested `text` components), one item per line so `list` components get a
     * usable `items` value from the sample markup the editor ships.
     *
     * @param  array<int, mixed>  $nodes
     */
    private function textOf(array $nodes): string
    {
        $lines = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $own = is_string($node['content'] ?? null) ? trim($node['content']) : '';
            $nested = is_array($node['components'] ?? null) ? $this->textOf($node['components']) : '';
            $text = $own !== '' ? $own : $nested;

            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Deterministic css-style id, honouring an explicit editor id when present.
     */
    private function nodeId(mixed $value, string $type, int &$counter): string
    {
        if (is_string($value) && preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $value) === 1) {
            return $value;
        }

        $counter++;

        return 'pb-'.$type.'-'.$counter;
    }

    /**
     * Only well-formed CSS class names survive the allowlist.
     *
     * @return list<string>
     */
    private function classes(mixed $classes): array
    {
        if (is_string($classes)) {
            $classes = preg_split('/\s+/', trim($classes)) ?: [];
        }

        if (! is_array($classes)) {
            return [];
        }

        $safe = [];

        foreach ($classes as $class) {
            if (is_string($class) && preg_match('/^[a-zA-Z0-9_-]+$/', $class) === 1) {
                $safe[] = $class;
            }
        }

        return $safe;
    }
}
