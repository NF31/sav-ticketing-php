<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Env;
use App\Enum\TicketStatut;
use App\Enum\TicketType;
use App\Repository\CommandeRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Les tickets crees ici sont supprimes en fin de test : les 3 tickets du
 * seed ne sont jamais modifies.
 */
final class TicketRepositoryTest extends TestCase
{
    private PDO $pdo;
    private TicketRepository $tickets;

    /** @var list<int> */
    private array $ticketsCrees = [];

    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../.env');
        $this->pdo = Database::connection();
        $this->tickets = new TicketRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        foreach ($this->ticketsCrees as $id) {
            $this->pdo->prepare('DELETE FROM tickets WHERE id = :id')->execute(['id' => $id]);
        }
    }

    /**
     * Regression : l'ancien code (TCK-0042-a1b2, 13 caracteres) depassait
     * tickets.code (VARCHAR(10)) et chaque creation plantait.
     */
    public function testCreerUnTicketDonneUnCodeDeriveDeLId(): void
    {
        $id = $this->tickets->create(1, TicketType::Reparation, 'Ecran casse');
        $this->ticketsCrees[] = $id;

        $ticket = $this->tickets->findById($id);

        self::assertNotNull($ticket);
        self::assertSame(sprintf('TCK-%04d', $id), $ticket->code);
        self::assertSame(TicketStatut::Nouveau, $ticket->statut);
        self::assertNull($ticket->technicienId);
        self::assertSame('Ecran casse', $ticket->description);
    }

    public function testDeuxTicketsOntDesCodesDifferents(): void
    {
        $a = $this->tickets->create(1, TicketType::Hotline, null);
        $b = $this->tickets->create(1, TicketType::Hotline, null);
        $this->ticketsCrees = [$a, $b];

        self::assertNotSame($this->tickets->findById($a)?->code, $this->tickets->findById($b)?->code);
    }

    /**
     * Verrouillage optimiste : si le statut a change depuis sa lecture (autre
     * utilisateur), la mise a jour est refusee et rien n'est journalise.
     */
    public function testChangerStatutRefuseSiLeStatutAChangeEntreTemps(): void
    {
        $id = $this->tickets->create(1, TicketType::Reparation, null);
        $this->ticketsCrees[] = $id;
        $adminId = (new UserRepository($this->pdo))->findByEmail('admin@sav.local')?->id ?? 0;

        // Deux utilisateurs lisent "nouveau" ; le premier passe en cours.
        self::assertTrue($this->tickets->changerStatut($id, TicketStatut::Nouveau, TicketStatut::EnCours, $adminId));
        // Le second, qui croit encore le ticket "nouveau", est refuse.
        self::assertFalse($this->tickets->changerStatut($id, TicketStatut::Nouveau, TicketStatut::EnAttentePiece, $adminId));

        self::assertSame(TicketStatut::EnCours, $this->tickets->findById($id)?->statut);
        self::assertCount(1, $this->tickets->historique($id));
    }

    public function testAssignerTechnicienRefuseUnTicketTermine(): void
    {
        $id = $this->tickets->create(1, TicketType::Reparation, null);
        $this->ticketsCrees[] = $id;
        $karimId = (new UserRepository($this->pdo))->findByEmail('karim.diallo@sav.local')?->id ?? 0;
        $this->pdo->prepare("UPDATE tickets SET statut = 'termine' WHERE id = :id")->execute(['id' => $id]);

        self::assertFalse($this->tickets->assignerTechnicien($id, $karimId));
        self::assertNull($this->tickets->findById($id)?->technicienId);
    }

    public function testCommandeExiste(): void
    {
        $commandes = new CommandeRepository($this->pdo);

        self::assertTrue($commandes->existe(1));
        self::assertFalse($commandes->existe(999999));
    }

    public function testFindTechnicienExclutLesAdmins(): void
    {
        $users = new UserRepository($this->pdo);
        $admin = $users->findByEmail('admin@sav.local');
        $technicien = $users->findByEmail('karim.diallo@sav.local');

        self::assertNotNull($admin);
        self::assertNotNull($technicien);
        self::assertNull($users->findTechnicien($admin->id));
        self::assertSame($technicien->id, $users->findTechnicien($technicien->id)?->id);
        self::assertNull($users->findTechnicien(999999));
    }

    /**
     * Les tickets crees dans la meme seconde ont le meme created_at : le tri
     * secondaire sur l'id les garde dans un ordre fixe d'une page a l'autre.
     */
    public function testFindPageDecoupeDuPlusRecentAuPlusAncien(): void
    {
        $a = $this->tickets->create(1, TicketType::Hotline, null);
        $b = $this->tickets->create(1, TicketType::Hotline, null);
        $c = $this->tickets->create(1, TicketType::Hotline, null);
        $this->ticketsCrees = [$a, $b, $c];

        $page1 = $this->tickets->findPage(null, 2, 0);
        $page2 = $this->tickets->findPage(null, 2, 2);

        self::assertSame([$c, $b], array_map(static fn ($t): int => $t->id, $page1));
        self::assertSame($a, $page2[0]->id);
        // Jointures faites apres la pagination : le nom du client est bien charge.
        self::assertNotSame('', trim($page1[0]->clientNomComplet));
    }

    public function testFindPageEtCompterAppliquentLeFiltreDeStatut(): void
    {
        $avant = $this->tickets->compter(TicketStatut::Nouveau);
        $totalAvant = $this->tickets->compter();

        $this->ticketsCrees[] = $this->tickets->create(1, TicketType::Reparation, null);

        self::assertSame($avant + 1, $this->tickets->compter(TicketStatut::Nouveau));
        self::assertSame($totalAvant + 1, $this->tickets->compter());

        $enCours = $this->tickets->findPage(TicketStatut::EnCours, 100, 0);
        self::assertCount($this->tickets->compter(TicketStatut::EnCours), $enCours);
        foreach ($enCours as $ticket) {
            self::assertSame(TicketStatut::EnCours, $ticket->statut);
        }
    }

    /** Index de la liste des tickets (schema.sql), dans le bon ordre de colonnes. */
    public function testIndexDeLaListeDesTicketsExistent(): void
    {
        $stmt = $this->pdo->query(
            "SELECT index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS colonnes
             FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = 'tickets'
               AND index_name IN ('idx_tickets_date', 'idx_tickets_statut_date')
             GROUP BY index_name"
        );

        self::assertSame(
            ['idx_tickets_date' => 'created_at', 'idx_tickets_statut_date' => 'statut,created_at'],
            $stmt->fetchAll(PDO::FETCH_KEY_PAIR),
        );
    }
}
