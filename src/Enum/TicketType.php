<?php

declare(strict_types=1);

namespace App\Enum;

enum TicketType: string
{
    case Reparation = 'reparation';
    case Expedition = 'expedition';
    case Hotline = 'hotline';
    // Demande de retour recue de kbeauty-shop (RabbitMQ), jamais saisie a la main.
    case Retour = 'retour';

    public function label(): string
    {
        return match ($this) {
            self::Reparation => 'Reparation',
            self::Expedition => 'Probleme expedition',
            self::Hotline => 'Question hotline',
            self::Retour => 'Retour client (boutique)',
        };
    }

    /** @return list<self> types proposes dans le formulaire de creation */
    public static function saisissables(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $type): bool => $type !== self::Retour));
    }
}
