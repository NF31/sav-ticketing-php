<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use PDO;
use PDOException;

final class ClientRepository
{
    public function __construct(private readonly PDO $pdo)
    {
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

    public function findIdParEmail(string $email): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM clients WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /** Cle stable cote kbeauty-customer-mdm (golden record), contrairement a l'email qui peut changer. */
    public function findIdParSourceUserId(int $sourceUserId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM clients WHERE source_user_id = :id LIMIT 1');
        $stmt->execute(['id' => $sourceUserId]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /**
     * Applique le golden record recu de kbeauty-customer-mdm. Ne touche pas
     * aux champs que le MDM ne connait pas (telephone, adresse).
     *
     * @return bool false si l'email cible est deja pris par un AUTRE client
     *              (contrainte UNIQUE) - le message est alors rejete plutot
     *              que d'ecraser un autre dossier.
     */
    public function mettreAJourIdentite(int $id, string $prenom, string $nom, string $email, int $sourceUserId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE clients SET nom = :nom, prenom = :prenom, email = :email, source_user_id = :source_user_id WHERE id = :id'
        );

        try {
            $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'source_user_id' => $sourceUserId,
                'id' => $id,
            ]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    public function emailExiste(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM clients WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return int|null l'id cree, null si l'email est deja pris (creation
     *                  simultanee passee entre emailExiste() et l'INSERT : la
     *                  contrainte UNIQUE tranche, sans erreur 500)
     */
    public function create(
        string $nom,
        string $prenom,
        string $email,
        ?string $telephone,
        ?string $rue,
        ?string $ville,
        ?string $codePostal,
        ?int $sourceUserId = null,
    ): ?int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO clients (nom, prenom, email, telephone, adresse_rue, adresse_ville, adresse_code_postal, source_user_id)
             VALUES (:nom, :prenom, :email, :telephone, :rue, :ville, :cp, :source_user_id)'
        );

        try {
            $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone,
                'rue' => $rue,
                'ville' => $ville,
                'cp' => $codePostal,
                'source_user_id' => $sourceUserId,
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
