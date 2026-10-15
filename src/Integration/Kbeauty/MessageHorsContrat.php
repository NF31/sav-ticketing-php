<?php

declare(strict_types=1);

namespace App\Integration\Kbeauty;

use RuntimeException;

/** Message RabbitMQ qui ne respecte pas son contrat (voir contracts/rabbitmq/). */
final class MessageHorsContrat extends RuntimeException
{
    /** @param list<string> $erreurs */
    public function __construct(array $erreurs)
    {
        parent::__construct('Message hors contrat : ' . implode('; ', $erreurs));
    }
}
