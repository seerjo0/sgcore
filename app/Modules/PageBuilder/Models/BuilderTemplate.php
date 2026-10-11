<?php

namespace App\Modules\PageBuilder\Models;

use App\Modules\PageBuilder\Services\Components\ComponentNode;
use Illuminate\Database\Eloquent\Model;

/**
 * Global builder template (site header/footer/404) built with the same
 * component engine as pages. Disabled rows never affect the public render.
 */
class BuilderTemplate extends Model
{
    public const TYPES = ['header', 'footer', 'not_found'];

    protected $fillable = [
        'type',
        'enabled',
        'version',
        'project',
        'components',
        'html',
        'css',
        'published_at',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'project' => 'array',
            'components' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return list<ComponentNode>|null
     */
    public function tree(): ?array
    {
        $raw = is_array($this->components) ? $this->components : null;

        if ($raw === null || $raw === []) {
            return null;
        }

        return array_values(array_map(
            static fn (array $node): ComponentNode => ComponentNode::fromArray($node),
            $raw,
        ));
    }
}
