<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationType;
use DateTimeImmutable;

final class Notification
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $ticketId,
        public readonly NotificationType $type,
        public readonly string $message,
        public readonly ?DateTimeImmutable $lueAt,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            ticketId: (int) $row['ticket_id'],
            type: NotificationType::from($row['type']),
            message: $row['message'],
            lueAt: $row['lue_at'] !== null ? new DateTimeImmutable($row['lue_at']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }

    public function estLue(): bool
    {
        return $this->lueAt !== null;
    }
}
