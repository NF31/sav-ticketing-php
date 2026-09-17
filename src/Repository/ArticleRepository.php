<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Entity\Article;
use PDO;

final class ArticleRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** @return list<Article> */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM articles ORDER BY designation');

        return array_map(Article::fromRow(...), $stmt->fetchAll());
    }

    public function findById(int $id): ?Article
    {
        $stmt = $this->pdo->prepare('SELECT * FROM articles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? Article::fromRow($row) : null;
    }

    public function ajusterStock(int $id, int $delta): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE articles SET stock_quantite = stock_quantite + :delta WHERE id = :id'
        );
        $stmt->execute(['delta' => $delta, 'id' => $id]);
    }
}
