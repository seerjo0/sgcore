<?php

namespace App\Modules\Media\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['gallery_id', 'media_id', 'sort_order', 'caption'])]
class GalleryItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Gallery that owns this item.
     *
     * @return BelongsTo<Gallery, $this>
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Media shown by this item.
     *
     * @return BelongsTo<Medium, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Medium::class);
    }
}
