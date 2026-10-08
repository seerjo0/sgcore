<?php

namespace App\Modules\Media\Models;

use App\Modules\Media\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
     * Items of this gallery, in display order.
     *
     * @return HasMany<GalleryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
