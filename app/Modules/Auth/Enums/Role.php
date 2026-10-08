<?php

namespace App\Modules\Auth\Enums;

enum Role: string
{
    case Admin = 'admin';

    case Editor = 'editor';

    /**
     * Human-readable label in pt-BR.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Editor => 'Editor',
        };
    }
}
