<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case Assignation = 'assignation';
    case Desassignation = 'desassignation';
    case Statut = 'statut';
    case NouveauTicket = 'nouveau_ticket';
}
