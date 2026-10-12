<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Enum\NotificationType;
use PDO;

final class NotificationRepository
{
    /** Nombre de notifications affichees sur la page Notifications. */
    public const int LIMITE_LISTE = 50;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function creer(int $userId, int $ticketId, NotificationType $type, string $message): void
    {
        $this->pdo->prepare(
            'INSERT INTO notifications (user_id, ticket_id, type, message)
             VALUES (:user_id, :ticket_id, :type, :message)'
        )->execute([
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'type' => $type->value,
            // VARCHAR(255) : un message trop long (nom tres long...) est coupe
            // plutot que de faire echouer l'insertion.
            'message' => mb_strimwidth($message, 0, 255, '...'),
        ]);
    }

    /** Pour le badge de la barre laterale, calcule sur chaque page (index user_id, lue_at). */
    public function compterNonLues(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND lue_at IS NULL');
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return list<Notification> les plus recentes d'abord */
    public function recentes(int $userId, int $limite = self::LIMITE_LISTE): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC, id DESC LIMIT :limite'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(Notification::fromRow(...), $stmt->fetchAll());
    }

    /**
     * Non lues arrivees apres $depuisId (ids croissants), pour les afficher
     * en direct dans la page deja ouverte.
     *
     * @return list<Notification> les plus recentes d'abord
     */
    public function nonLuesDepuis(int $userId, int $depuisId, int $limite): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM notifications
             WHERE user_id = :user_id AND lue_at IS NULL AND id > :depuis
             ORDER BY id DESC LIMIT :limite'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('depuis', $depuisId, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(Notification::fromRow(...), $stmt->fetchAll());
    }

    /** Id de la derniere notification de l'utilisateur (0 s'il n'en a aucune) : point de depart du suivi en direct. */
    public function derniereId(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM notifications WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Ouvrir un ticket vaut lecture de ses notifications : pas besoin de
     * les marquer une a une. Limite a l'utilisateur donne, jamais celles
     * d'un autre.
     */
    public function marquerLuesPourTicket(int $userId, int $ticketId): void
    {
        $this->pdo->prepare(
            'UPDATE notifications SET lue_at = NOW() WHERE user_id = :user_id AND ticket_id = :ticket_id AND lue_at IS NULL'
        )->execute(['user_id' => $userId, 'ticket_id' => $ticketId]);
    }

    public function marquerToutesLues(int $userId): void
    {
        $this->pdo->prepare('UPDATE notifications SET lue_at = NOW() WHERE user_id = :user_id AND lue_at IS NULL')
            ->execute(['user_id' => $userId]);
    }
}
