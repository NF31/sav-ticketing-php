<?php

declare(strict_types=1);

namespace App\Entity;

final class Article
{
    public function __construct(
        public readonly int $id,
        public readonly string $reference,
        public readonly string $designation,
        public readonly float $prix,
        public readonly int $stockQuantite,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            reference: $row['reference'],
            designation: $row['designation'],
            prix: (float) $row['prix'],
            stockQuantite: (int) $row['stock_quantite'],
        );
    }
}
