<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'json',
    ];
}
