<?php

namespace App\Modules\Media\Models;

use App\Models\User;
use App\Modules\Media\Factories\MediumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['filename', 'path', 'mime_type', 'size', 'width', 'height', 'alt', 'caption', 'uploaded_by'])]
class Medium extends Model
{
    /** @use HasFactory<MediumFactory> */
    use HasFactory;

    protected $table = 'media';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * The factory to use when the model is a module class.
     */
    protected static function newFactory(): MediumFactory
    {
        return MediumFactory::new();
    }

    /**
     * Delete the underlying file whenever the record is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (Medium $medium): void {
            Storage::disk('local')->delete('media/'.$medium->path);
        });
    }

    /**
     * Public URL used to serve this file.
     */
    public function url(): string
    {
        return '/media/'.$this->path;
    }

    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime_type, 'video/');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /**
     * 'video' | 'image' — what the library grid, the picker and the editor's
     * media browser render for this record.
     */
    public function kind(): string
    {
        return $this->isVideo() ? 'video' : 'image';
    }

    /**
     * User who uploaded the file.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
