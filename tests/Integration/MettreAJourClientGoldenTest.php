<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Conteneur;
use App\Core\Database;
use App\Core\Env;
use App\Integration\Kbeauty\ContratClientMiseAJour;
use App\Service\MettreAJourClientGolden;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ClientGoldenRecord;

/**
 * Applique un golden record client sur la vraie base et avec le vrai
 * cablage (config/services.php), comme le consommateur RabbitMQ. Les
 * clients crees ici (emails @exemple.test) sont supprimes en fin de test.
 */
final class MettreAJourClientGoldenTest extends TestCase
{
    private PDO $pdo;
    private MettreAJourClientGolden $service;
    private ContratClientMiseAJour $contrat;

    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../.env');
        $this->pdo = Database::connection();
        $conteneur = new Conteneur();
        (require __DIR__ . '/../../config/services.php')($conteneur);
        $this->service = $conteneur->get(MettreAJourClientGolden::class);
        $this->contrat = new ContratClientMiseAJour();
        $this->nettoyer();
    }

    protected function tearDown(): void
    {
        $this->nettoyer();
    }

    public function testUnClientInconnuEstCreeAvecSonIdentifiantSource(): void
    {
        $applique = $this->service->appliquer($this->contrat->valider(ClientGoldenRecord::json()));

        self::assertTrue($applique);
        self::assertSame(
            ['nom' => 'Martin', 'prenom' => 'Jeanne', 'email' => 'jeanne.martin@exemple.test', 'source_user_id' => 7],
            $this->pdo->query("SELECT nom, prenom, email, source_user_id FROM clients WHERE email = 'jeanne.martin@exemple.test'")->fetch(),
        );
    }

    /**
     * Ferme le risque de divergence : un client cree via un retour, jamais
     * revu, voit son nom mis a jour, retrouve par email (seule cle connue
     * tant que source_user_id n'a jamais ete lie) - celui-ci est backfill
     * au passage.
     */
    public function testUnClientExistantRetrouveParEmailEstMisAJourEtBackfillSourceUserId(): void
    {
        $this->pdo->exec("INSERT INTO clients (nom, prenom, email) VALUES ('Martin', 'Jeanne', 'jeanne.martin@exemple.test')");

        $applique = $this->service->appliquer($this->contrat->valider(ClientGoldenRecord::json([
            'name' => 'Jeanne Durand',
            'email' => 'jeanne.martin@exemple.test',
        ])));

        self::assertTrue($applique);
        self::assertSame(
            ['nom' => 'Durand', 'prenom' => 'Jeanne', 'email' => 'jeanne.martin@exemple.test', 'source_user_id' => 7],
            $this->pdo->query("SELECT nom, prenom, email, source_user_id FROM clients WHERE source_user_id = 7")->fetch(),
        );
    }

    public function testUneMiseAJourSuivanteRetrouveLeClientParSourceUserIdMemeApresChangementDEmail(): void
    {
        $this->service->appliquer($this->contrat->valider(ClientGoldenRecord::json(['email' => 'jeanne.martin@exemple.test'])));
        $this->service->appliquer($this->contrat->valider(ClientGoldenRecord::json(['name' => 'Jeanne Durand', 'email' => 'jeanne.durand@exemple.test'])));

        self::assertSame(1, $this->compter('SELECT COUNT(*) FROM clients WHERE source_user_id = 7'));
        self::assertSame(
            ['nom' => 'Durand', 'email' => 'jeanne.durand@exemple.test'],
            $this->pdo->query('SELECT nom, email FROM clients WHERE source_user_id = 7')->fetch(),
        );
    }

    public function testUnConflitDEmailAvecUnAutreClientEstRejete(): void
    {
        $this->pdo->exec("INSERT INTO clients (nom, prenom, email) VALUES ('Dupont', 'Marc', 'marc.dupont@exemple.test')");
        $this->pdo->exec("INSERT INTO clients (source_user_id, nom, prenom, email) VALUES (7, 'Martin', 'Jeanne', 'jeanne.martin@exemple.test')");

        $applique = $this->service->appliquer($this->contrat->valider(ClientGoldenRecord::json([
            'email' => 'marc.dupont@exemple.test',
        ])));

        self::assertFalse($applique);
        self::assertSame('jeanne.martin@exemple.test', $this->pdo->query('SELECT email FROM clients WHERE source_user_id = 7')->fetchColumn());
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
