<?php

namespace App\Modules\Media\Models;

use App\Modules\Media\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title'])]
class Gallery extends Model
{
    /** @use HasFactory<GalleryFactory> */
    use HasFactory;

    /**
     * The factory to use when the model is a module class.
     */
    protected static function newFactory(): GalleryFactory
    {
        return GalleryFactory::new();
    }

    /**
     * Display order chosen in the panel (manual sort_order; when equal,
     * newest first — the pre-1.9 behavior themes/modules kept).
     *
     * @param  Builder<Gallery>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('id');
    }

    /**
     * Items of this gallery, in display order.
     *
     * @return HasMany<GalleryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
