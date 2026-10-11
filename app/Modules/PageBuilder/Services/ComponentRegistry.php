<?php

namespace App\Modules\PageBuilder\Services;

use App\Modules\PageBuilder\Services\Components\Component;
use App\Modules\PageBuilder\Services\Components\ComponentNode;
use Illuminate\Support\Facades\Validator;

/**
 * The allowlisted component registry: every type the editor can drop and the
 * public renderer can emit. Renders a validated component tree through the
 * registered Blade views and never falls back to arbitrary user templates.
 */
class ComponentRegistry
{
    /**
     * @var array<string, Component>
     */
    private array $components = [];

    public function __construct(private ProjectExtractor $extractor) {}

    /**
     * Register a renderable component by its type id.
     */
    public function register(Component $component): void
    {
        $this->components[$component->type()] = $component;
    }

    public function get(string $type): ?Component
    {
        return $this->components[$type] ?? null;
    }

    /**
     * @return array<string, Component>
     */
    public function all(): array
    {
        return $this->components;
    }

    /**
     * @return array<string, Component>
     */
    public function byGroup(string $group): array
    {
        return array_filter($this->components, fn (Component $c): bool => $c->group() === $group);
    }

    /**
     * Whether the given project contains at least one renderable component.
     */
    public function isTyped(?array $project): bool
    {
        return $this->extract($project) !== null;
    }

    /**
     * @param  array<string, mixed>|null  $project
     * @return list<ComponentNode>|null
     */
    public function extract(?array $project): ?array
    {
        return $this->extractor->extract($project);
    }

    /**
     * Render an extracted component tree to HTML. Invalid or unknown nodes are
     * skipped (allowlist) so a corrupted payload degrades, never explodes.
     *
     * @param  list<ComponentNode>  $nodes
     */
    public function renderTree(array $nodes, RenderContext $context): string
    {
        $html = '';

        foreach ($nodes as $node) {
            $children = $this->renderTree($node->children, $context);
            $html .= $this->renderNode($node, $context, $children);
        }

        return $html;
    }

    public function renderNode(ComponentNode $node, RenderContext $context, string $childrenHtml): string
    {
        $component = $this->get($node->type);

        if ($component === null || ! $this->passes($component, $node->props)) {
            return '';
        }

        $node = new ComponentNode(
            type: $node->type,
            id: $node->id,
            classes: $node->classes,
            style: $node->style,
            props: $context->resolveProps($node->props),
            children: $node->children,
        );

        return $component->render($node, $childrenHtml);
    }

    /**
     * Editor metadata for every registered component.
     *
     * @return array<int, array<string, mixed>>
     */
    public function metadata(): array
    {
        $out = [];

        foreach ($this->components as $component) {
            $out[] = [
                'type' => $component->type(),
                'name' => $component->name(),
                'group' => $component->group(),
                'props' => $component->props(),
                'defaults' => $component->defaults(),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function passes(Component $component, array $props): bool
    {
        $rules = $component->rules();

        if ($rules === []) {
            return true;
        }

        return Validator::make([...$component->defaults(), ...$props], $rules)->passes();
    }
}
