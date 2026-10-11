<?php

namespace App\Modules\Pages\Models;

use App\Modules\Media\Models\Medium;
use App\Modules\PageBuilder\Models\BuilderRevision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'slug',
    'content',
    'status',
    'is_home',
    'seo_title',
    'seo_description',
    'seo_og_image',
    'published_at',
    'builder_draft',
    'builder_published',
    'builder_version',
])]
class Page extends Model
{
    /**
     * Slugs that would collide with system routes (reserved for the app).
     *
     * @var list<string>
     */
    public const RESERVED_SLUGS = ['admin', 'instalar', 'media', 'storage', 'theme-assets', 'up'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_home' => 'boolean',
            'published_at' => 'datetime',
            'builder_draft' => 'array',
            'builder_published' => 'array',
            'builder_version' => 'integer',
        ];
    }

    /**
     * Body HTML stored under the "body" content key.
     */
    public function body(): ?string
    {
        $body = $this->content['body'] ?? null;

        return is_string($body) ? $body : null;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Image used by Open Graph / social sharing.
     */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Medium::class, 'seo_og_image');
    }

    /**
     * Builder publish snapshots, newest first.
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(BuilderRevision::class)->orderByDesc('version');
    }
}
