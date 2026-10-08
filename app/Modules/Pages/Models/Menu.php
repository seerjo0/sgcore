<?php

namespace App\Modules\Pages\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'location'])]
class Menu extends Model
{
    /**
     * Known menu locations (pt-BR labels) — one menu per location.
     *
     * @var array<string, string>
     */
    public const LOCATIONS = [
        'primary' => 'Principal (topo)',
        'footer' => 'Rodapé',
    ];

    /**
     * Items of this menu in display order (flat; build the tree with parent_id).
     *
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Top-level items of this menu, in display order.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }
}
