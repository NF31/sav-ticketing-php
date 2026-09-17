<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Entity\Ticket;
use App\Enum\TicketStatut;
use App\Enum\TicketType;
use PDO;

final class TicketRepository
{
    private PDO $pdo;

    private const string SELECT_BASE = <<<'SQL'
        SELECT
            t.*,
            CONCAT(c.prenom, ' ', c.nom) AS client_nom_complet,
            CONCAT(u.prenom, ' ', u.nom) AS technicien_nom_complet
        FROM tickets t
        INNER JOIN commandes co ON co.id = t.commande_id
        INNER JOIN clients c ON c.id = co.client_id
        LEFT JOIN users u ON u.id = t.technicien_id
        SQL;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * @param TicketStatut|null $statut filtre optionnel, ex. dashboard "tickets en cours"
     * @return list<Ticket>
     */
    public function findAll(?TicketStatut $statut = null): array
    {
        $sql = self::SELECT_BASE;
        $params = [];

        if ($statut !== null) {
            $sql .= ' WHERE t.statut = :statut';
            $params['statut'] = $statut->value;
        }

        $sql .= ' ORDER BY t.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(Ticket::fromRow(...), $stmt->fetchAll());
    }

    public function findById(int $id): ?Ticket
    {
        $stmt = $this->pdo->prepare(self::SELECT_BASE . ' WHERE t.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? Ticket::fromRow($row) : null;
    }

    /** @return list<Ticket> assignes a un technicien donne */
    public function findByTechnicien(int $technicienId): array
    {
        $stmt = $this->pdo->prepare(self::SELECT_BASE . ' WHERE t.technicien_id = :id ORDER BY t.created_at DESC');
        $stmt->execute(['id' => $technicienId]);

        return array_map(Ticket::fromRow(...), $stmt->fetchAll());
    }

    public function create(
        string $code,
        int $commandeId,
        ?int $technicienId,
        TicketType $type,
        ?string $description,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (code, commande_id, technicien_id, type, statut, description)
             VALUES (:code, :commande_id, :technicien_id, :type, :statut, :description)'
        );
        $stmt->execute([
            'code' => $code,
            'commande_id' => $commandeId,
            'technicien_id' => $technicienId,
            'type' => $type->value,
            'statut' => TicketStatut::Nouveau->value,
            'description' => $description,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Met a jour le statut d'un ticket et journalise le changement dans
     * ticket_historique, dans la meme transaction : ajout par rapport a
     * l'original, qui n'avait aucune tracabilite des changements de statut.
     */
    public function changerStatut(int $ticketId, TicketStatut $ancienStatut, TicketStatut $nouveauStatut, int $userId): void
    {
        $this->pdo->beginTransaction();

        try {
            $update = $this->pdo->prepare('UPDATE tickets SET statut = :statut WHERE id = :id');
            $update->execute(['statut' => $nouveauStatut->value, 'id' => $ticketId]);

            $historique = $this->pdo->prepare(
                'INSERT INTO ticket_historique (ticket_id, ancien_statut, nouveau_statut, user_id)
                 VALUES (:ticket_id, :ancien, :nouveau, :user_id)'
            );
            $historique->execute([
                'ticket_id' => $ticketId,
                'ancien' => $ancienStatut->value,
                'nouveau' => $nouveauStatut->value,
                'user_id' => $userId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function assignerTechnicien(int $ticketId, int $technicienId): void
    {
        $stmt = $this->pdo->prepare('UPDATE tickets SET technicien_id = :tech WHERE id = :id');
        $stmt->execute(['tech' => $technicienId, 'id' => $ticketId]);
    }

    /** @return list<array{ancien_statut: ?string, nouveau_statut: string, user_nom: string, created_at: string}> */
    public function historique(int $ticketId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT h.ancien_statut, h.nouveau_statut, h.created_at, CONCAT(u.prenom, ' ', u.nom) AS user_nom
             FROM ticket_historique h
             INNER JOIN users u ON u.id = h.user_id
             WHERE h.ticket_id = :id
             ORDER BY h.created_at ASC"
        );
        $stmt->execute(['id' => $ticketId]);

        return $stmt->fetchAll();
    }
}
