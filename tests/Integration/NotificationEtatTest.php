<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controller\NotificationController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Enum\NotificationType;
use App\Enum\TicketType;
use App\Repository\NotificationRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use PDO;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Reponse JSON de /notifications/etat (interrogee par notifications.js).
 * Processus separe : le controleur envoie des en-tetes HTTP, impossible
 * apres la sortie deja affichee par PHPUnit.
 */
final class NotificationEtatTest extends TestCase
{
    private PDO $pdo;
    private NotificationRepository $notifications;
    private int $karimId;
    private int $leaId;
    private int $ticketId;

    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../.env');
        $_SESSION = [];
        $this->pdo = Database::connection();
        $users = new UserRepository($this->pdo);
        $this->notifications = new NotificationRepository($this->pdo);
        $this->karimId = $users->findByEmail('karim.diallo@sav.local')?->id ?? 0;
        $this->leaId = $users->findByEmail('lea.nguyen@sav.local')?->id ?? 0;
        $this->ticketId = (new TicketRepository($this->pdo))->create(1, TicketType::Hotline, null);
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM tickets WHERE id = :id')->execute(['id' => $this->ticketId]);
    }

    #[RunInSeparateProcess]
    public function testRenvoieLesNouvellesDeLUtilisateurConnecteUniquement(): void
    {
        $depart = $this->notifications->derniereId($this->karimId);
        $this->notifications->creer($this->karimId, $this->ticketId, NotificationType::Assignation, 'Pour <b>Karim</b>');
        $this->notifications->creer($this->leaId, $this->ticketId, NotificationType::Assignation, 'Pour Lea');

        $etat = $this->etat(['depuis' => (string) $depart]);

        self::assertSame($this->notifications->compterNonLues($this->karimId), $etat['nonLues']);
        self::assertSame($this->notifications->derniereId($this->karimId), $etat['derniereId']);
        self::assertCount(1, $etat['nouvelles']);
        // Message brut : notifications.js l'insere en texte (textContent), jamais en HTML.
        self::assertSame('Pour <b>Karim</b>', $etat['nouvelles'][0]['message']);
        self::assertSame('/tickets/' . $this->ticketId, $etat['nouvelles'][0]['url']);
    }

    /** Premier appel d'une page : point de depart seulement, rien d'ancien a afficher. */
    #[RunInSeparateProcess]
    public function testSansPointDeDepartAucuneNouvelle(): void
    {
        $this->notifications->creer($this->karimId, $this->ticketId, NotificationType::Statut, 'Ancienne');

        foreach ([[], ['depuis' => 'abc'], ['depuis' => '-1']] as $query) {
            $etat = $this->etat($query);

            self::assertSame([], $etat['nouvelles']);
            self::assertSame($this->notifications->derniereId($this->karimId), $etat['derniereId']);
        }
    }

    /**
     * @param array<string, string> $query
     * @return array{nonLues: int, derniereId: int, nouvelles: list<array{id: int, message: string, url: string}>}
     */
    private function etat(array $query): array
    {
        $auth = new Auth(new UserRepository($this->pdo));
        self::assertTrue($auth->attempt('karim.diallo@sav.local', 'password'));

        ob_start();
        (new NotificationController($auth, $this->notifications))->etat(new Request('GET', '/notifications/etat', query: $query));

        return json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
    }
}
