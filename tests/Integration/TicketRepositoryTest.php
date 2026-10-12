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
}
