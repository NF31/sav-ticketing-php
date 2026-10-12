<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Ticket;
use App\Enum\TicketStatut;
use App\Enum\TicketType;
use PDO;

final class TicketRepository
{
    private const string COLONNES = <<<'SQL'
        SELECT
            t.*,
            CONCAT(c.prenom, ' ', c.nom) AS client_nom_complet,
            CONCAT(u.prenom, ' ', u.nom) AS technicien_nom_complet
        SQL;

    private const string JOINTURES = <<<'SQL'
        INNER JOIN commandes co ON co.id = t.commande_id
        INNER JOIN clients c ON c.id = co.client_id
        LEFT JOIN users u ON u.id = t.technicien_id
        SQL;

    private const string SELECT_BASE = self::COLONNES . ' FROM tickets t ' . self::JOINTURES;

    private const string ORDRE_LISTE = ' ORDER BY t.created_at DESC, t.id DESC';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Une page de tickets, du plus recent au plus ancien.
     *
     * Jointure differee : le sous-select choisit d'abord les ids de la page
     * sur la seule table tickets, en parcourant l'index idx_tickets_date ou
     * idx_tickets_statut_date dans l'ordre (InnoDB ajoute la cle primaire a
     * chaque index secondaire : l'index suffit, sans lire la table). Les
     * jointures ne portent ensuite que sur ces lignes. En une seule requete
     * avec jointures, MariaDB partait de commandes, lisait tous les tickets
     * puis les triait (verifie avec EXPLAIN sur 20 000 tickets : ~47 ms
     * par page contre ~3,5 ms ici, pour les memes resultats).
     *
     * t.id en second critere de tri : plusieurs tickets peuvent avoir le
     * meme created_at (a la seconde pres), et sans ordre total un ticket
     * pourrait apparaitre sur deux pages, ou sur aucune.
     *
     * @param TicketStatut|null $statut filtre optionnel, ex. "tickets en cours"
     * @return list<Ticket>
     */
    public function findPage(?TicketStatut $statut, int $limite, int $offset): array
    {
        [$where, $params] = self::filtreStatut($statut);

        $stmt = $this->pdo->prepare(
            self::COLONNES
            . ' FROM (SELECT t.id FROM tickets t' . $where . self::ORDRE_LISTE . ' LIMIT :limite OFFSET :offset) page'
            . ' INNER JOIN tickets t ON t.id = page.id '
            . self::JOINTURES
            . self::ORDRE_LISTE
        );

        foreach ($params as $cle => $valeur) {
            $stmt->bindValue($cle, $valeur);
        }

        // PARAM_INT explicite : LIMIT et OFFSET n'acceptent pas une chaine.
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(Ticket::fromRow(...), $stmt->fetchAll());
    }

    /** Nombre total de tickets (avec le meme filtre que findPage), pour la pagination. */
    public function compter(?TicketStatut $statut = null): int
    {
        // Pas de jointure : chaque ticket a une commande et un client (cles
        // etrangeres NOT NULL), compter sur tickets seul donne le meme total.
        [$where, $params] = self::filtreStatut($statut);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tickets t' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Clause WHERE en texte fixe : la valeur du statut passe uniquement par
     * un parametre, jamais dans le SQL (RequetesSqlTest le verifie).
     *
     * @return array{string, array<string, string>} clause WHERE et ses parametres
     */
    private static function filtreStatut(?TicketStatut $statut): array
    {
        return $statut === null
            ? ['', []]
            : [' WHERE t.statut = :statut', ['statut' => $statut->value]];
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
    /**
     * @param int|null $retourKbeautyId demande de retour kbeauty-shop a l'origine
     *                                  du ticket (import) ; UNIQUE en base
     */
    public function create(
        int $commandeId,
        TicketType $type,
        ?string $description,
        ?int $retourKbeautyId = null,
    ): int {
        $this->pdo->beginTransaction();

        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO tickets (code, commande_id, type, statut, description, retour_kbeauty_id)
                 VALUES (:code, :commande_id, :type, :statut, :description, :retour_kbeauty_id)'
            );
            $insert->execute([
                'code' => 'TMP-' . bin2hex(random_bytes(3)),
                'commande_id' => $commandeId,
                'type' => $type->value,
                'statut' => TicketStatut::Nouveau->value,
                'description' => $description,
                'retour_kbeauty_id' => $retourKbeautyId,
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

    /** Ticket deja cree pour cette demande de retour kbeauty-shop, s'il existe. */
    public function findIdParRetourKbeauty(int $retourKbeautyId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM tickets WHERE retour_kbeauty_id = :id LIMIT 1');
        $stmt->execute(['id' => $retourKbeautyId]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public static function code(int $id): string
    {
        return sprintf('TCK-%04d', $id);
    }

    /**
     * Met a jour le statut d'un ticket et journalise le changement dans
     * ticket_historique, dans la meme transaction : ajout par rapport a
     * l'original, qui n'avait aucune tracabilite des changements de statut.
     *
     * Verrouillage optimiste : la mise a jour n'a lieu que si le ticket a
     * toujours $ancienStatut. Deux changements simultanes depuis le meme
     * statut ne passent donc pas tous les deux.
     *
     * @return bool false si le ticket n'a plus $ancienStatut (rien n'est ecrit)
     */
    public function changerStatut(int $ticketId, TicketStatut $ancienStatut, TicketStatut $nouveauStatut, int $userId): bool
    {
        $this->pdo->beginTransaction();

        try {
            $update = $this->pdo->prepare('UPDATE tickets SET statut = :statut WHERE id = :id AND statut = :ancien');
            $update->execute(['statut' => $nouveauStatut->value, 'id' => $ticketId, 'ancien' => $ancienStatut->value]);

            if ($update->rowCount() === 0) {
                $this->pdo->rollBack();

                return false;
            }

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

        return true;
    }

    /** @return bool false si le ticket n'existe pas ou est termine (rien n'est ecrit) */
    public function assignerTechnicien(int $ticketId, int $technicienId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE tickets SET technicien_id = :tech WHERE id = :id AND statut <> :termine');
        $stmt->execute(['tech' => $technicienId, 'id' => $ticketId, 'termine' => TicketStatut::Termine->value]);

        return $stmt->rowCount() > 0;
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
