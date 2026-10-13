<?php

namespace App\Modules\Blog\Models;

use App\Modules\Blog\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * The factory to use when the model is a module class.
     */
    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }
}
