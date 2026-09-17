<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Poste;
use App\Enum\Role;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly Role $role,
        public readonly ?Poste $poste,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            nom: $row['nom'],
            prenom: $row['prenom'],
            email: $row['email'],
            passwordHash: $row['password_hash'],
            role: Role::from($row['role']),
            poste: $row['poste'] !== null ? Poste::from($row['poste']) : null,
        );
    }

    public function nomComplet(): string
    {
        return "{$this->prenom} {$this->nom}";
    }
}
