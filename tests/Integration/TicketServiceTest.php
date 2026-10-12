<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\ApresReponse;
use App\Core\Database;
use App\Core\Env;
use App\Entity\User;
use App\Enum\TicketStatut;
use App\Repository\CommandeRepository;
use App\Repository\NotificationRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\Exception\RegleMetierViolee;
use App\Service\Exception\SaisieInvalide;
use App\Service\Exception\TicketIntrouvable;
use App\Service\Notifieur;
use App\Service\TicketService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MailerEspion;

/**
 * Regles metier des tickets, sur la vraie base. Les tickets crees ici sont
 * supprimes en fin de test (leur historique part avec, ON DELETE CASCADE).
 */
final class TicketServiceTest extends TestCase
{
    private PDO $pdo;
    private TicketService $service;
    private TicketRepository $tickets;
    private UserRepository $users;

    /** @var list<int> */
    private array $ticketsCrees = [];

    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../.env');
        $this->pdo = Database::connection();
        $this->tickets = new TicketRepository($this->pdo);
        $this->users = new UserRepository($this->pdo);
        // Les notifications sont testees dans NotificationsTest.
        $notifieur = new Notifieur(new NotificationRepository($this->pdo), $this->users, new MailerEspion(), 'http://localhost');
        $this->service = new TicketService($this->tickets, new CommandeRepository($this->pdo), $this->users, $notifieur);
    }

    protected function tearDown(): void
    {
        ApresReponse::executerTaches();

        foreach ($this->ticketsCrees as $id) {
            $this->pdo->prepare('DELETE FROM tickets WHERE id = :id')->execute(['id' => $id]);
        }
    }

    public function testCreerEnregistreUnTicketNouveau(): void
    {
        $id = $this->creerTicket();

        $ticket = $this->tickets->findById($id);
        self::assertSame(TicketStatut::Nouveau, $ticket?->statut);
        self::assertSame('Ecran casse', $ticket?->description);
    }

    public function testCreerAvecUneSaisieInvalideNEnregistreRien(): void
    {
        $avant = $this->nombreDeTickets();

        try {
            $this->service->creer(['commande_id' => '999999', 'type' => 'inconnu', 'description' => '  texte  '], $this->admin());
            self::fail('SaisieInvalide attendue.');
        } catch (SaisieInvalide $e) {
            self::assertSame(['commande_id', 'type'], array_keys($e->erreurs));
            // Valeurs normalisees, pour reafficher le formulaire.
            self::assertSame('texte', $e->donnees['description']);
        }

        self::assertSame($avant, $this->nombreDeTickets());
    }

    public function testChangerStatutSuitLaMachineAEtatsEtJournalise(): void
    {
        $id = $this->creerTicket();

        $this->service->changerStatut($id, TicketStatut::EnCours, $this->admin());

        self::assertSame(TicketStatut::EnCours, $this->tickets->findById($id)?->statut);
        $historique = $this->tickets->historique($id);
        self::assertCount(1, $historique);
        self::assertSame('nouveau', $historique[0]['ancien_statut']);
        self::assertSame('en_cours', $historique[0]['nouveau_statut']);
    }

    public function testChangerStatutRefuseUneTransitionInterdite(): void
    {
        $id = $this->creerTicket();

        try {
            $this->service->changerStatut($id, TicketStatut::Termine, $this->admin());
            self::fail('RegleMetierViolee attendue.');
        } catch (RegleMetierViolee $e) {
            self::assertSame('Transition de statut non autorisee (Nouveau -> Termine).', $e->getMessage());
        }

        self::assertSame(TicketStatut::Nouveau, $this->tickets->findById($id)?->statut);
        self::assertSame([], $this->tickets->historique($id));
    }

    public function testChangerStatutDUnTicketInexistant(): void
    {
        $this->expectException(TicketIntrouvable::class);

        $this->service->changerStatut(999999, TicketStatut::EnCours, $this->admin());
    }

    public function testAssignerUnTechnicien(): void
    {
        $id = $this->creerTicket();
        $karim = $this->users->findByEmail('karim.diallo@sav.local');

        $technicien = $this->service->assigner($id, $karim?->id, $this->admin());

        self::assertSame($karim?->id, $technicien->id);
        self::assertSame($karim?->id, $this->tickets->findById($id)?->technicienId);
    }

    /** Aucune ligne changee en base, mais ce n'est pas un echec (ATTR_FOUND_ROWS). */
    public function testReassignerLeMemeTechnicienReussit(): void
    {
        $id = $this->creerTicket();
        $karimId = $this->users->findByEmail('karim.diallo@sav.local')?->id;

        $this->service->assigner($id, $karimId, $this->admin());
        $technicien = $this->service->assigner($id, $karimId, $this->admin());

        self::assertSame($karimId, $technicien->id);
    }

    public function testAssignerUnTicketTermineEstRefuse(): void
    {
        $id = $this->creerTicket();
        $this->pdo->prepare("UPDATE tickets SET statut = 'termine' WHERE id = :id")->execute(['id' => $id]);

        $this->expectExceptionObject(new RegleMetierViolee('Un ticket termine ne peut plus etre reassigne.'));

        $this->service->assigner($id, $this->users->findByEmail('karim.diallo@sav.local')?->id, $this->admin());
    }

    public function testAssignerSansTechnicienEstRefuse(): void
    {
        $this->expectExceptionObject(new RegleMetierViolee('Choisissez un technicien.'));

        $this->service->assigner($this->creerTicket(), null, $this->admin());
    }

    public function testAssignerUnAdminEstRefuse(): void
    {
        $id = $this->creerTicket();

        try {
            $this->service->assigner($id, $this->admin()->id, $this->admin());
            self::fail('RegleMetierViolee attendue.');
        } catch (RegleMetierViolee $e) {
            self::assertStringStartsWith('Technicien introuvable', $e->getMessage());
        }

        self::assertNull($this->tickets->findById($id)?->technicienId);
    }

    public function testAssignerUnTicketInexistant(): void
    {
        $this->expectException(TicketIntrouvable::class);

        $this->service->assigner(999999, $this->users->findByEmail('karim.diallo@sav.local')?->id, $this->admin());
    }

    private function creerTicket(): int
    {
        $id = $this->service->creer(['commande_id' => '1', 'type' => 'reparation', 'description' => 'Ecran casse'], $this->admin());
        $this->ticketsCrees[] = $id;

        return $id;
    }

    private function admin(): User
    {
        return $this->users->findByEmail('admin@sav.local') ?? throw new \LogicException('Admin du seed absent.');
    }

    private function nombreDeTickets(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
    }
}
