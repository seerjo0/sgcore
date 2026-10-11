<?php

namespace App\Modules\PageBuilder\Services\Components;

/**
 * A single node of the structured component tree extracted from a GrapesJS
 * project and rendered by the server-side registry. Props are key/value pairs
 * coming from `data-pb-*` attributes (canonical source edited through traits),
 * plus derived rich content for text-like leaves.
 */
class ComponentNode
{
    /**
     * @param  list<string>  $classes
     * @param  array<string, mixed>  $props
     * @param  list<ComponentNode>  $children
     */
    public function __construct(
        public string $type,
        public string $id,
        public array $classes,
        public string $style,
        public array $props,
        public array $children = [],
    ) {}

    /**
     * Rebuild a node from its persisted (json) form. Malformed entries collapse
     * to safe defaults so a corrupted payload never renders arbitrary data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $children = [];

        foreach (is_array($data['children'] ?? null) ? $data['children'] : [] as $child) {
            if (is_array($child)) {
                $children[] = self::fromArray($child);
            }
        }

        return new self(
            type: (string) ($data['type'] ?? ''),
            id: (string) ($data['id'] ?? ''),
            classes: array_values(array_filter((array) ($data['classes'] ?? []), 'is_string')),
            style: (string) ($data['style'] ?? ''),
            props: is_array($data['props'] ?? null) ? $data['props'] : [],
            children: $children,
        );
    }
}
