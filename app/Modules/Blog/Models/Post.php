<?php

namespace App\Modules\Blog\Models;

use App\Models\User;
use App\Modules\Blog\Factories\PostFactory;
use App\Modules\Media\Models\Medium;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'title',
    'slug',
    'excerpt',
    'content',
    'status',
    'category_id',
    'cover_image',
    'author_id',
    'seo_title',
    'seo_description',
    'seo_og_image',
    'published_at',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Published posts, newest first (the public archive ordering).
     *
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');
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
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Medium::class, 'cover_image');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Medium::class, 'seo_og_image');
    }

    /**
     * The factory to use when the model is a module class.
     */
    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }
}
