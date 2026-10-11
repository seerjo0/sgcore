<?php

namespace App\Modules\PageBuilder\Services\Components;

/**
 * A page-builder component: an allowlisted, server-rendered building block.
 *
 * Every component knows its own type id (used by `data-pb-type` in the
 * editor), its user-facing metadata, the editable properties (traits), the
 * Laravel validation rules applied on publish and again at render time, and
 * the Blade view that renders it. Rich/HTML props are always run through the
 * BuilderSanitizer before reaching the template.
 *
 * To add a new component, subclass this class (or instantiate it directly)
 * and register the instance in the ComponentRegistry from a service provider:
 *
 *     $registry->register(new class extends Component {
 *         public function type(): string { return 'icon_box'; }
 *         public function name(): string { return 'Caixa com ícone'; }
 *         public function group(): string { return 'content'; }
 *         public function props(): array {
 *             return ['text' => ['label' => 'Texto', 'type' => 'text']];
 *         }
 *         public function rules(): array { return ['text' => ['required', 'string', 'max:255']]; }
 *         public function view(): string { return 'pagebuilder::components.icon_box'; }
 *     });
 *
 * The view receives `$props`, `$children` (pre-rendered inner HTML) and
 * `$node`. Override `render()` for full control.
 */
abstract class Component
{
    abstract public function type(): string;

    /**
     * Human-readable name (already translated).
     */
    abstract public function name(): string;

    /**
     * Editor category: layout | content | components.
     */
    abstract public function group(): string;

    /**
     * Editor metadata for each key of `props`: ['label', 'type']
     * where type is text | textarea | select | number.
     *
     * @return array<string, array{label: string, type: string, options?: list<string>}>
     */
    abstract public function props(): array;

    /**
     * Laravel validation rules for the props dictionary.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * Blade view used to render the component.
     */
    abstract public function view(): string;

    /**
     * Default props merged underneath what the editor sent.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [];
    }

    /**
     * Render the component. Escaping happens inside the Blade theme
     * (standard `{{ }}`); HTML-ish props are sanitized beforehand.
     */
    public function render(ComponentNode $node, string $childrenHtml): string
    {
        $data = [
            'props' => [...$this->defaults(), ...$node->props],
            'children' => $childrenHtml,
            'node' => $node,
            'component' => $this,
        ];

        return (string) view($this->view(), $data)->render();
    }
}
