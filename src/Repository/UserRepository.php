<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Entity\User;
use PDO;

final class UserRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * C'est ici que le projet original recuperait TOUTE la table users
     * (SELECT motdepasse, poste FROM techniciens, sans WHERE) puis
     * comparait en PHP. On filtre desormais en SQL avec une requete
     * preparee : un seul enregistrement recupere, jamais les autres
     * mots de passe hashes.
     */
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row !== false ? User::fromRow($row) : null;
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? User::fromRow($row) : null;
    }

    /** @return list<User> */
    public function findTechniciens(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM users WHERE role = 'technicien' ORDER BY nom");

        return array_map(User::fromRow(...), $stmt->fetchAll());
    }

    /** @return list<User> */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM users ORDER BY role, nom');

        return array_map(User::fromRow(...), $stmt->fetchAll());
    }
}
