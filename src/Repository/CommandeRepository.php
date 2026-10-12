<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use PDOException;

final class CommandeRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array{id: int, date_commande: string, client_nom_complet: string}> */
    public function findAllAvecClient(): array
    {
        $stmt = $this->pdo->query(
            "SELECT co.id, co.date_commande, CONCAT(c.prenom, ' ', c.nom) AS client_nom_complet
             FROM commandes co
             INNER JOIN clients c ON c.id = co.client_id
             ORDER BY co.date_commande DESC"
        );

        return $stmt->fetchAll();
    }

    public function existe(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM commandes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    public function create(int $clientId, string $dateCommande): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO commandes (client_id, date_commande) VALUES (:client_id, :date)');
        $stmt->execute(['client_id' => $clientId, 'date' => $dateCommande]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Commande importee d'une autre application (ex. kbeauty-shop), retrouvee
     * par son numero d'origine : un second retour sur la meme commande la
     * reutilise. La contrainte UNIQUE tranche si deux imports la creent en
     * meme temps.
     */
    public function trouverOuCreerParReference(string $reference, int $clientId, string $dateCommande): int
    {
        $existante = $this->findIdParReference($reference);
        if ($existante !== null) {
            return $existante;
        }

        try {
            $this->pdo->prepare(
                'INSERT INTO commandes (client_id, date_commande, reference_externe) VALUES (:client_id, :date, :reference)'
            )->execute(['client_id' => $clientId, 'date' => $dateCommande, 'reference' => $reference]);
        } catch (PDOException $e) {
            // 1062 = entree en double : creee entre-temps par un autre import.
            if (($e->errorInfo[1] ?? null) === 1062) {
                return $this->findIdParReference($reference) ?? throw $e;
            }

            throw $e;
        }

        return (int) $this->pdo->lastInsertId();
    }

    private function findIdParReference(string $reference): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM commandes WHERE reference_externe = :reference LIMIT 1');
        $stmt->execute(['reference' => $reference]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public function ajouterArticle(int $commandeId, int $articleId, int $quantite): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO commande_articles (commande_id, article_id, quantite) VALUES (:commande_id, :article_id, :quantite)'
        );
        $stmt->execute(['commande_id' => $commandeId, 'article_id' => $articleId, 'quantite' => $quantite]);
    }

    /** @return list<array{designation: string, quantite: int, prix: string}> */
    public function articlesDeLaCommande(int $commandeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.designation, ca.quantite, a.prix
             FROM commande_articles ca
             INNER JOIN articles a ON a.id = ca.article_id
             WHERE ca.commande_id = :id'
        );
        $stmt->execute(['id' => $commandeId]);

        return $stmt->fetchAll();
    }
}
