<?php

declare(strict_types=1);

namespace App\Entity;

final class Client
{
    public function __construct(
        public readonly int $id,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly string $email,
        public readonly ?string $telephone,
        public readonly ?string $adresseRue,
        public readonly ?string $adresseVille,
        public readonly ?string $adresseCodePostal,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            nom: $row['nom'],
            prenom: $row['prenom'],
            email: $row['email'],
            telephone: $row['telephone'],
            adresseRue: $row['adresse_rue'],
            adresseVille: $row['adresse_ville'],
            adresseCodePostal: $row['adresse_code_postal'],
        );
    }

    public function nomComplet(): string
    {
        return "{$this->prenom} {$this->nom}";
    }
}
