<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use PDO;

final class CommandeRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
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

    public function create(int $clientId, string $dateCommande): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO commandes (client_id, date_commande) VALUES (:client_id, :date)');
        $stmt->execute(['client_id' => $clientId, 'date' => $dateCommande]);

        return (int) $this->pdo->lastInsertId();
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
