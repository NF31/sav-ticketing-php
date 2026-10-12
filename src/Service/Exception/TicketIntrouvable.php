<?php

declare(strict_types=1);

namespace App\Service\Exception;

use RuntimeException;

final class TicketIntrouvable extends RuntimeException
{
    public function __construct(public readonly int $ticketId)
    {
        parent::__construct("Ticket introuvable : {$ticketId}.");
    }
}
