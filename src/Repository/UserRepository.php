<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Entity\User;
use App\Enum\Poste;
use App\Enum\Role;
use PDO;
use PDOException;

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

    public function emailExiste(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return int|null l'id cree, null si l'email est deja pris : cas ou deux
     *                  creations simultanees passent toutes les deux la
     *                  verification emailExiste() avant l'INSERT ; la contrainte
     *                  UNIQUE tranche, sans erreur 500.
     */
    public function creerTechnicien(string $nom, string $prenom, string $email, string $motDePasse, Poste $poste): ?int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (nom, prenom, email, password_hash, role, poste)
             VALUES (:nom, :prenom, :email, :hash, :role, :poste)'
        );

        try {
            $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'hash' => password_hash($motDePasse, PASSWORD_BCRYPT),
                'role' => Role::Technicien->value,
                'poste' => $poste->value,
            ]);
        } catch (PDOException $e) {
            // 1062 = entree en double sur une cle UNIQUE (ici l'email).
            if (($e->errorInfo[1] ?? null) === 1062) {
                return null;
            }

            throw $e;
        }

        return (int) $this->pdo->lastInsertId();
    }
}
