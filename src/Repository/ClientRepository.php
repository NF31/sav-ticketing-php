<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Entity\Client;
use PDO;

final class ClientRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** @return list<Client> */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM clients ORDER BY nom, prenom');

        return array_map(Client::fromRow(...), $stmt->fetchAll());
    }

    public function findById(int $id): ?Client
    {
        $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? Client::fromRow($row) : null;
    }

    public function create(
        string $nom,
        string $prenom,
        string $email,
        ?string $telephone,
        ?string $rue,
        ?string $ville,
        ?string $codePostal,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO clients (nom, prenom, email, telephone, adresse_rue, adresse_ville, adresse_code_postal)
             VALUES (:nom, :prenom, :email, :telephone, :rue, :ville, :cp)'
        );
        $stmt->execute([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'rue' => $rue,
            'ville' => $ville,
            'cp' => $codePostal,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
