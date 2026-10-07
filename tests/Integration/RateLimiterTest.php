<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Env;
use App\Core\RateLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private const string CLE = 'test:rate-limiter';

    private PDO $pdo;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../.env');
        $this->pdo = Database::connection();
        $this->limiter = new RateLimiter($this->pdo);
        $this->nettoyer();
    }

    protected function tearDown(): void
    {
        $this->nettoyer();
    }

    public function testBloqueAPartirDuMaximumAtteint(): void
    {
        $this->limiter->enregistrer(self::CLE);
        $this->limiter->enregistrer(self::CLE);
        self::assertFalse($this->limiter->tropDeTentatives(self::CLE, 3, 60));

        $this->limiter->enregistrer(self::CLE);
        self::assertTrue($this->limiter->tropDeTentatives(self::CLE, 3, 60));
    }

    public function testLesTentativesHorsFenetreNeComptentPas(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->enregistrer(self::CLE);
        }
        $this->pdo->prepare('UPDATE tentatives SET created_at = NOW() - INTERVAL 61 MINUTE WHERE cle = :cle')
            ->execute(['cle' => self::CLE]);

        self::assertFalse($this->limiter->tropDeTentatives(self::CLE, 3, 60));
    }

    public function testLesClesSontIndependantes(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->enregistrer(self::CLE);
        }

        self::assertFalse($this->limiter->tropDeTentatives(self::CLE . ':autre', 3, 60));
    }

    private function nettoyer(): void
    {
        $this->pdo->prepare('DELETE FROM tentatives WHERE cle LIKE :prefixe')->execute(['prefixe' => self::CLE . '%']);
    }
}
