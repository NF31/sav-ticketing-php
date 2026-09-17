<?php

declare(strict_types=1);

namespace App\Enum;

enum Role: string
{
    case Admin = 'admin';
    case Technicien = 'technicien';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Technicien => 'Technicien',
        };
    }
}
