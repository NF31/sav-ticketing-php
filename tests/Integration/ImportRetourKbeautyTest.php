<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Conteneur;
use App\Core\Database;
use App\Core\Env;
use App\Enum\NotificationType;
use App\Enum\TicketType;
use App\Integration\Kbeauty\ContratRetourSoumis;
use App\Repository\TicketRepository;
use App\Service\ImportRetourKbeauty;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\RetourKbeauty;

/**
 * Import d'une demande de retour kbeauty-shop en ticket, sur la vraie base et
 * avec le vrai cablage (config/services.php), comme le consommateur RabbitMQ.
 * Les clients crees ici (emails @exemple.test) sont supprimes en fin de test,
 * avec leurs commandes, tickets et notifications (ON DELETE CASCADE).
 */
final class ImportRetourKbeautyTest extends TestCase
{
    private PDO $pdo;
    private ImportRetourKbeauty $import;
    private TicketRepository $tickets;
    private ContratRetourSoumis $contrat;

    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../.env');
        $this->pdo = Database::connection();
        $conteneur = new Conteneur();
        (require __DIR__ . '/../../config/services.php')($conteneur);
        $this->import = $conteneur->get(ImportRetourKbeauty::class);
        $this->tickets = $conteneur->get(TicketRepository::class);
        $this->contrat = new ContratRetourSoumis();
        $this->nettoyer();
    }

    protected function tearDown(): void
    {
        $this->nettoyer();
    }

    public function testUnRetourDevientUnTicketANotifierAuxAdmins(): void
    {
        $ticketId = $this->import->importer($this->contrat->valider(RetourKbeauty::json()));

        self::assertNotNull($ticketId);
        $ticket = $this->tickets->findById($ticketId) ?? self::fail('Ticket absent.');
        self::assertSame(TicketType::Retour, $ticket->type);
        self::assertSame('Jeanne Martin', $ticket->clientNomComplet);
        self::assertSame(
            "Motif du client :\nFlacon arrive fele.\n\n"
            . "Articles retournes :\n- 2 x Serum Centella - 50 ml (49,00 EUR)\n- 1 x Creme Snail (18,90 EUR)\n\n"
            . 'Demande de retour n°42 de la boutique, commande KB-2026-00042.',
            $ticket->description,
        );

        $commande = $this->pdo->query("SELECT date_commande, reference_externe FROM commandes WHERE reference_externe = 'KB-2026-00042'")->fetch();
        self::assertSame(['date_commande' => '2026-10-01', 'reference_externe' => 'KB-2026-00042'], $commande);

        $notification = $this->pdo->prepare('SELECT type, message FROM notifications WHERE ticket_id = :id');
        $notification->execute(['id' => $ticketId]);
        self::assertSame(
            ['type' => NotificationType::NouveauTicket->value, 'message' => sprintf('Nouveau retour client TCK-%04d (commande KB-2026-00042) a assigner, recu de la boutique.', $ticketId)],
            $notification->fetch(),
        );
    }

    /** RabbitMQ livre "au moins une fois" : le meme message recu deux fois ne cree qu'un ticket. */
    public function testUnMessageRecuDeuxFoisNeCreeQuUnTicket(): void
    {
        $retour = $this->contrat->valider(RetourKbeauty::json());

        $premier = $this->import->importer($retour);
        $second = $this->import->importer($retour);

        self::assertNotNull($premier);
        self::assertNull($second);
        self::assertSame(1, $this->compter("SELECT COUNT(*) FROM tickets WHERE retour_kbeauty_id = 42"));
        self::assertSame(1, $this->compter("SELECT COUNT(*) FROM commandes WHERE reference_externe = 'KB-2026-00042'"));
    }

    public function testUnSecondRetourSurLaMemeCommandeReutiliseClientEtCommande(): void
    {
        $this->import->importer($this->contrat->valider(RetourKbeauty::json()));
        $this->import->importer($this->contrat->valider(RetourKbeauty::json(['returnRequestId' => 43])));

        self::assertSame(2, $this->compter("SELECT COUNT(*) FROM tickets t JOIN commandes c ON c.id = t.commande_id WHERE c.reference_externe = 'KB-2026-00042'"));
        self::assertSame(1, $this->compter("SELECT COUNT(*) FROM commandes WHERE reference_externe = 'KB-2026-00042'"));
        self::assertSame(1, $this->compter("SELECT COUNT(*) FROM clients WHERE email = 'jeanne.martin@exemple.test'"));
    }

    public function testUnClientDejaConnuDuSavEstReutiliseSansEtreModifie(): void
    {
        $this->pdo->exec("INSERT INTO clients (nom, prenom, email, telephone) VALUES ('Martin', 'Jeanne-Marie', 'jeanne.martin@exemple.test', '0601020304')");

        $ticketId = $this->import->importer($this->contrat->valider(RetourKbeauty::json()));

        self::assertSame('Jeanne-Marie Martin', $this->tickets->findById((int) $ticketId)?->clientNomComplet);
        self::assertSame(1, $this->compter("SELECT COUNT(*) FROM clients WHERE email = 'jeanne.martin@exemple.test' AND telephone = '0601020304'"));
    }

    public function testSeparationDuNom(): void
    {
        self::assertSame(['Jeanne', 'Martin'], ImportRetourKbeauty::separerNom('Jeanne Martin'));
        self::assertSame(['Jeanne', 'De La Tour'], ImportRetourKbeauty::separerNom('  Jeanne   De La Tour '));
        self::assertSame(['', 'Cher'], ImportRetourKbeauty::separerNom('Cher'));
    }

    private function compter(string $sql): int
    {
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    private function nettoyer(): void
    {
        $this->pdo->exec("DELETE FROM clients WHERE email LIKE '%@exemple.test'");
    }
}
