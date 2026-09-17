<?php

declare(strict_types=1);

namespace App\Enum;

enum TicketType: string
{
    case Reparation = 'reparation';
    case Expedition = 'expedition';
    case Hotline = 'hotline';

    public function label(): string
    {
        return match ($this) {
            self::Reparation => 'Reparation',
            self::Expedition => 'Probleme expedition',
            self::Hotline => 'Question hotline',
        };
    }
}
