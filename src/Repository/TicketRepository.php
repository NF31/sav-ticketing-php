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

    /**
     * Le code (TCK-0042) est derive de l'id, donc unique et sequentiel
     * comme ceux du seed. L'id n'etant connu qu'apres l'INSERT, le ticket
     * est insere avec un code provisoire puis renomme, dans une transaction
     * pour qu'aucun ticket ne reste avec son code provisoire.
     * Le code tient dans tickets.code (VARCHAR(10)) jusqu'a l'id 999999 ;
     * l'ancien code (TCK-0042-a1b2, 13 caracteres) n'y tenait jamais, d'ou
     * une erreur 500 a chaque creation de ticket.
     */
    public function create(
        int $commandeId,
        TicketType $type,
        ?string $description,
    ): int {
        $this->pdo->beginTransaction();

        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO tickets (code, commande_id, type, statut, description)
                 VALUES (:code, :commande_id, :type, :statut, :description)'
            );
            $insert->execute([
                'code' => 'TMP-' . bin2hex(random_bytes(3)),
                'commande_id' => $commandeId,
                'type' => $type->value,
                'statut' => TicketStatut::Nouveau->value,
                'description' => $description,
            ]);
            $id = (int) $this->pdo->lastInsertId();

            $this->pdo->prepare('UPDATE tickets SET code = :code WHERE id = :id')
                ->execute(['code' => self::code($id), 'id' => $id]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        return $id;
    }

    public static function code(int $id): string
    {
        return sprintf('TCK-%04d', $id);
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
