<?php

namespace App\Modules\PageBuilder\Models;

use App\Modules\Pages\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable snapshot of a builder publish: the structured component tree plus
 * the server-rendered HTML/CSS and the raw GrapesJS project needed to restore
 * the editor draft on rollback.
 */
class BuilderRevision extends Model
{
    protected $table = 'page_revisions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'page_id',
        'version',
        'components',
        'html',
        'css',
        'project',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'components' => 'array',
            'project' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
