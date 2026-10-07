<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Limitation de debit sur fenetre glissante, stockee en base (table
 * tentatives). Usage : verifier tropDeTentatives() avant l'action, puis
 * enregistrer() la tentative.
 */
final class RateLimiter
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    public function tropDeTentatives(string $cle, int $max, int $fenetreMinutes): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tentatives
             WHERE cle = :cle AND created_at > NOW() - INTERVAL :minutes MINUTE'
        );
        $stmt->execute(['cle' => $cle, 'minutes' => $fenetreMinutes]);

        return (int) $stmt->fetchColumn() >= $max;
    }

    public function enregistrer(string $cle): void
    {
        $this->pdo->prepare('INSERT INTO tentatives (cle) VALUES (:cle)')->execute(['cle' => $cle]);

        // Purge au fil de l'eau plutot qu'un cron : aucune fenetre utilisee
        // dans l'application ne depasse 24 h.
        $this->pdo->exec('DELETE FROM tentatives WHERE created_at < NOW() - INTERVAL 1 DAY');
    }

    /** Remet le compteur a zero, ex. apres une connexion reussie. */
    public function effacer(string $cle): void
    {
        $this->pdo->prepare('DELETE FROM tentatives WHERE cle = :cle')->execute(['cle' => $cle]);
    }
}
