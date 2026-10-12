<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * Jetons de reinitialisation de mot de passe. Le jeton en clair n'existe
 * que dans le lien envoye par email ; la base ne stocke que son hash
 * SHA-256. Un hash rapide suffit ici (pas besoin de bcrypt) : le jeton
 * fait 256 bits aleatoires, il n'est pas devinable par force brute
 * contrairement a un mot de passe choisi par un humain.
 */
final class PasswordResetRepository
{
    public const int DUREE_VALIDITE_MINUTES = 60;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Cree un jeton pour l'utilisateur et invalide les precedents encore
     * actifs : seul le dernier lien demande fonctionne.
     *
     * @return string le jeton en clair, a mettre dans le lien (jamais stocke)
     */
    public function creerJeton(int $userId): string
    {
        $jeton = bin2hex(random_bytes(32));

        $this->pdo->beginTransaction();

        try {
            $this->invaliderJetonsActifs($userId);

            // Expiration calculee par MariaDB (NOW()) et verifiee par MariaDB :
            // une seule horloge, pas de decalage de fuseau possible avec PHP.
            $stmt = $this->pdo->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at)
                 VALUES (:user_id, :hash, NOW() + INTERVAL :minutes MINUTE)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'hash' => self::hasher($jeton),
                'minutes' => self::DUREE_VALIDITE_MINUTES,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        return $jeton;
    }

    /** @return int|null l'id de l'utilisateur si le jeton est valide (existant, non utilise, non expire) */
    public function trouverUtilisateurPourJeton(string $jeton): ?int
    {
        if (!self::formatValide($jeton)) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT user_id FROM password_resets
             WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['hash' => self::hasher($jeton)]);
        $userId = $stmt->fetchColumn();

        return $userId !== false ? (int) $userId : null;
    }

    /**
     * Change le mot de passe et consomme le jeton dans la meme transaction.
     * Le jeton est re-verifie ici (et pas seulement a l'affichage du
     * formulaire) : il a pu expirer ou etre utilise entre-temps.
     *
     * @return bool false si le jeton n'est plus valide
     */
    public function reinitialiserMotDePasse(string $jeton, string $nouveauMotDePasse): bool
    {
        $this->pdo->beginTransaction();

        try {
            $userId = $this->trouverUtilisateurPourJeton($jeton);

            if ($userId === null) {
                $this->pdo->rollBack();

                return false;
            }

            $update = $this->pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $update->execute([
                'hash' => password_hash($nouveauMotDePasse, PASSWORD_BCRYPT),
                'id' => $userId,
            ]);

            $this->invaliderJetonsActifs($userId);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        return true;
    }

    private function invaliderJetonsActifs(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :id AND used_at IS NULL');
        $stmt->execute(['id' => $userId]);
    }

    private static function hasher(string $jeton): string
    {
        return hash('sha256', $jeton);
    }

    private static function formatValide(string $jeton): bool
    {
        return strlen($jeton) === 64 && ctype_xdigit($jeton);
    }
}
