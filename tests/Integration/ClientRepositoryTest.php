<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Env;
use App\Repository\ClientRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class ClientRepositoryTest extends TestCase
{
    private const string EMAIL_TEST = 'client.test@example.com';

    private PDO $pdo;
    private ClientRepository $clients;

    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../.env');
        $this->pdo = Database::connection();
        $this->clients = new ClientRepository($this->pdo);
        $this->supprimerClientTest();
    }

    protected function tearDown(): void
    {
        $this->supprimerClientTest();
    }

    public function testCreationEtEmailExiste(): void
    {
        self::assertFalse($this->clients->emailExiste(self::EMAIL_TEST));

        $id = $this->clients->create('Test', 'Client', self::EMAIL_TEST, '0601020304', null, null, null);

        self::assertNotNull($id);
        self::assertTrue($this->clients->emailExiste(self::EMAIL_TEST));
        self::assertSame('0601020304', $this->clients->findById($id)?->telephone);
    }

    public function testEmailDejaPrisRenvoieNullSansException(): void
    {
        // Cas de deux creations simultanees : la contrainte UNIQUE tranche.
        self::assertNull($this->clients->create('Test', 'Doublon', 'jean.dupont@example.com', null, null, null, null));
    }

    private function supprimerClientTest(): void
    {
        $this->pdo->prepare('DELETE FROM clients WHERE email = :email')->execute(['email' => self::EMAIL_TEST]);
    }
}
