<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TicketStatut;
use App\Enum\TicketType;
use DateTimeImmutable;

final class Ticket
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly int $commandeId,
        public readonly ?int $technicienId,
        public readonly TicketType $type,
        public readonly TicketStatut $statut,
        public readonly ?string $description,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        // Champs joints, remplis uniquement par les requetes qui en ont besoin.
        public readonly ?string $clientNomComplet = null,
        public readonly ?string $technicienNomComplet = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            code: $row['code'],
            commandeId: (int) $row['commande_id'],
            technicienId: $row['technicien_id'] !== null ? (int) $row['technicien_id'] : null,
            type: TicketType::from($row['type']),
            statut: TicketStatut::from($row['statut']),
            description: $row['description'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            clientNomComplet: $row['client_nom_complet'] ?? null,
            technicienNomComplet: $row['technicien_nom_complet'] ?? null,
        );
    }
}
