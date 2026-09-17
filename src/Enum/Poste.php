<?php

declare(strict_types=1);

namespace App\Enum;

enum Poste: string
{
    case Sav = 'sav';
    case Hotline = 'hotline';

    public function label(): string
    {
        return match ($this) {
            self::Sav => 'SAV terrain',
            self::Hotline => 'Hotline',
        };
    }
}
