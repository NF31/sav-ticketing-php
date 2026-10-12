<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\ApresReponse;
use App\Core\Database;
use App\Core\Env;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\TicketStatut;
use App\Repository\CommandeRepository;
use App\Repository\NotificationRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\Notifieur;
use App\Service\TicketService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MailerEspion;

/**
 * Qui est notifie, sur la vraie base, en passant par TicketService comme
 * l'application. Les tickets crees ici sont supprimes en fin de test, et
 * leurs notifications avec (ON DELETE CASCADE).
 */
final class NotificationsTest extends TestCase
{
    private PDO $pdo;
    private TicketService $service;
    private TicketRepository $tickets;
    private NotificationRepository $notifications;
    private MailerEspion $mailer;
    private Notifieur $notifieur;
    private User $admin;
    private User $karim;
    private User $lea;

    /** @var list<int> */
    private array $ticketsCrees = [];

    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../.env');
        $this->pdo = Database::connection();
        $users = new UserRepository($this->pdo);
        $this->tickets = new TicketRepository($this->pdo);
        $this->notifications = new NotificationRepository($this->pdo);
        $this->mailer = new MailerEspion();
        $this->notifieur = new Notifieur($this->notifications, $users, $this->mailer, 'https://sav.exemple.fr/');
        $this->service = new TicketService($this->tickets, new CommandeRepository($this->pdo), $users, $this->notifieur);

        $this->admin = $users->findByEmail('admin@sav.local') ?? throw new \LogicException('Seed absent.');
        $this->karim = $users->findByEmail('karim.diallo@sav.local') ?? throw new \LogicException('Seed absent.');
        $this->lea = $users->findByEmail('lea.nguyen@sav.local') ?? throw new \LogicException('Seed absent.');
    }

    protected function tearDown(): void
    {
        ApresReponse::executerTaches();

        foreach ($this->ticketsCrees as $id) {
            $this->pdo->prepare('DELETE FROM tickets WHERE id = :id')->execute(['id' => $id]);
        }
    }

    public function testAssignationNotifieLeTechnicienEtLuiEnvoieUnEmail(): void
    {
        $id = $this->creerTicket();

        $this->service->assigner($id, $this->karim->id, $this->admin);

        self::assertSame(
            [[NotificationType::Assignation, sprintf('Le ticket TCK-%04d vous a ete assigne par Sophie Admin.', $id)]],
            $this->notificationsDe($this->karim, $id),
        );

        // Email envoye apres la reponse, pas pendant la requete.
        self::assertSame([], $this->mailer->envoyes);
        ApresReponse::executerTaches();

        self::assertCount(1, $this->mailer->envoyes);
        self::assertSame('karim.diallo@sav.local', $this->mailer->envoyes[0]['destinataire']);
        self::assertSame(sprintf('Ticket TCK-%04d assigne', $id), $this->mailer->envoyes[0]['sujet']);
        self::assertStringContainsString("https://sav.exemple.fr/tickets/{$id}\n", $this->mailer->envoyes[0]['corps']);
        self::assertStringContainsString('client Jean Dupont', $this->mailer->envoyes[0]['corps']);
    }

    public function testReassignationPrevientAussiLAncienTechnicien(): void
    {
        $id = $this->creerTicket();
        $this->service->assigner($id, $this->karim->id, $this->admin);

        $this->service->assigner($id, $this->lea->id, $this->admin);

        self::assertSame(NotificationType::Assignation, $this->notificationsDe($this->lea, $id)[0][0]);
        self::assertSame(
            [NotificationType::Desassignation, sprintf('Le ticket TCK-%04d a ete reassigne a Lea Nguyen par Sophie Admin.', $id)],
            $this->notificationsDe($this->karim, $id)[1],
        );
    }

    public function testReassignerLeMemeTechnicienNeNotifiePasDeNouveau(): void
    {
        $id = $this->creerTicket();
        $this->service->assigner($id, $this->karim->id, $this->admin);

        $this->service->assigner($id, $this->karim->id, $this->admin);
        ApresReponse::executerTaches();

        self::assertCount(1, $this->notificationsDe($this->karim, $id));
        self::assertCount(1, $this->mailer->envoyes);
    }

    public function testUnTechnicienQuiSAssigneLuiMemeNEstPasNotifie(): void
    {
        $id = $this->creerTicket();

        $this->service->assigner($id, $this->karim->id, $this->karim);
        ApresReponse::executerTaches();

        self::assertSame([], $this->notificationsDe($this->karim, $id));
        self::assertSame([], $this->mailer->envoyes);
    }

    public function testChangementDeStatutNotifieLeTechnicienSaufSIlEnEstLAuteur(): void
    {
        $id = $this->creerTicket();
        $this->service->assigner($id, $this->karim->id, $this->admin);

        $this->service->changerStatut($id, TicketStatut::EnCours, $this->admin);
        $this->service->changerStatut($id, TicketStatut::EnAttentePiece, $this->karim);

        $notifs = $this->notificationsDe($this->karim, $id);
        self::assertCount(2, $notifs);
        self::assertSame(
            [NotificationType::Statut, sprintf('Le ticket TCK-%04d est passe de "Nouveau" a "En cours" (par Sophie Admin).', $id)],
            $notifs[1],
        );
    }

    public function testStatutDUnTicketNonAssigneNeNotifiePersonne(): void
    {
        $id = $this->creerTicket();

        $this->service->changerStatut($id, TicketStatut::EnCours, $this->admin);

        self::assertSame(0, $this->nombreDeNotificationsDuTicket($id));
    }

    public function testUnTicketCreeParUnTechnicienEstSignaleAuxAdmins(): void
    {
        $id = $this->creerTicket($this->karim);

        self::assertSame(
            [[NotificationType::NouveauTicket, sprintf('Nouveau ticket TCK-%04d a assigner (cree par Karim Diallo).', $id)]],
            $this->notificationsDe($this->admin, $id),
        );
    }

    public function testUnAdminNEstPasNotifieDuTicketQuIlACree(): void
    {
        $id = $this->creerTicket($this->admin);

        self::assertSame([], $this->notificationsDe($this->admin, $id));
    }

    /** L'action est deja enregistree : une notification impossible a ecrire ne doit pas la faire echouer. */
    public function testUneNotificationEnEchecNeLevePasDException(): void
    {
        $id = $this->creerTicket();
        $ticket = $this->tickets->findById($id) ?? throw new \LogicException('Ticket absent.');
        $technicienInexistant = new User(999999, 'X', 'Y', 'x@y.z', '', \App\Enum\Role::Technicien, null);

        // Cle etrangere violee (user_id 999999) : erreur journalisee, pas levee.
        $ancienLog = ini_set('error_log', '/dev/null');
        try {
            $this->notifieur->ticketAssigne($ticket, $technicienInexistant, $this->admin);
        } finally {
            ini_set('error_log', (string) $ancienLog);
        }

        self::assertSame(0, $this->nombreDeNotificationsDuTicket($id));
    }

    public function testCompteurEtLectureDesNotifications(): void
    {
        $a = $this->creerTicket();
        $b = $this->creerTicket();
        $this->service->assigner($a, $this->karim->id, $this->admin);
        $this->service->assigner($b, $this->karim->id, $this->admin);
        $this->service->assigner($b, $this->lea->id, $this->admin);
        $karimAvant = $this->notifications->compterNonLues($this->karim->id);
        $leaAvant = $this->notifications->compterNonLues($this->lea->id);

        // Ouvrir le ticket A ne lit que les notifications de Karim sur A.
        $this->notifications->marquerLuesPourTicket($this->karim->id, $a);

        self::assertSame($karimAvant - 1, $this->notifications->compterNonLues($this->karim->id));
        self::assertSame($leaAvant, $this->notifications->compterNonLues($this->lea->id));

        $this->notifications->marquerToutesLues($this->karim->id);

        self::assertSame(0, $this->notifications->compterNonLues($this->karim->id));
        self::assertSame($leaAvant, $this->notifications->compterNonLues($this->lea->id));
    }

    public function testRecentesDuPlusRecentAuPlusAncienEtLimitees(): void
    {
        $a = $this->creerTicket();
        $b = $this->creerTicket();
        $this->service->assigner($a, $this->lea->id, $this->admin);
        $this->service->assigner($b, $this->lea->id, $this->admin);

        $recentes = $this->notifications->recentes($this->lea->id, 2);

        self::assertSame([$b, $a], array_map(static fn ($n): int => $n->ticketId, $recentes));
        self::assertFalse($recentes[0]->estLue());
    }

    /** Suivi en direct : seules les non lues posterieures au point de depart sont renvoyees. */
    public function testNonLuesDepuisUnPointDeDepart(): void
    {
        $a = $this->creerTicket();
        $this->service->assigner($a, $this->lea->id, $this->admin);
        $depart = $this->notifications->derniereId($this->lea->id);

        $b = $this->creerTicket();
        $c = $this->creerTicket();
        $this->service->assigner($b, $this->lea->id, $this->admin);
        $this->service->assigner($c, $this->lea->id, $this->admin);
        $this->notifications->marquerLuesPourTicket($this->lea->id, $c);

        $nouvelles = $this->notifications->nonLuesDepuis($this->lea->id, $depart, 5);

        self::assertSame([$b], array_map(static fn ($n): int => $n->ticketId, $nouvelles));
        self::assertGreaterThan($depart, $this->notifications->derniereId($this->lea->id));
        self::assertSame(0, $this->notifications->derniereId(999999));
    }

    private function creerTicket(?User $auteur = null): int
    {
        $id = $this->service->creer(['commande_id' => '1', 'type' => 'reparation', 'description' => ''], $auteur ?? $this->admin);
        $this->ticketsCrees[] = $id;

        return $id;
    }

    /** @return list<array{NotificationType, string}> notifications de $user sur ce ticket, des plus anciennes aux plus recentes */
    private function notificationsDe(User $user, int $ticketId): array
    {
        $stmt = $this->pdo->prepare('SELECT type, message FROM notifications WHERE user_id = :u AND ticket_id = :t ORDER BY id');
        $stmt->execute(['u' => $user->id, 't' => $ticketId]);

        return array_map(
            static fn (array $row): array => [NotificationType::from($row['type']), $row['message']],
            $stmt->fetchAll(),
        );
    }

    private function nombreDeNotificationsDuTicket(int $ticketId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE ticket_id = :t');
        $stmt->execute(['t' => $ticketId]);

        return (int) $stmt->fetchColumn();
    }
}
