<?php

namespace App\Modules\PageBuilder\Services\Components;

/**
 * Config-based Component used by the built-in set. Custom widgets may extend
 * Component directly and override render() for bespoke behavior.
 */
final class GenericComponent extends Component
{
    /**
     * @param  array<string, array{label: string, type: string, options?: list<string>}>  $props
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $defaults
     */
    public function __construct(
        private readonly string $type,
        private readonly string $name,
        private readonly string $group,
        private readonly string $view,
        private readonly array $props = [],
        private readonly array $rules = [],
        private readonly array $defaults = [],
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function group(): string
    {
        return $this->group;
    }

    public function props(): array
    {
        return $this->props;
    }

    public function rules(): array
    {
        return $this->rules;
    }

    public function view(): string
    {
        return $this->view;
    }

    public function defaults(): array
    {
        return $this->defaults;
    }
}
