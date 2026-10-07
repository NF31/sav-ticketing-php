<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Env;
use App\Repository\PasswordResetRepository;
use App\Repository\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tourne contre la vraie base, sur un utilisateur cree pour le test et
 * supprime ensuite (ses jetons partent avec, ON DELETE CASCADE) : les
 * comptes de demo du seed ne sont jamais modifies.
 */
final class PasswordResetRepositoryTest extends TestCase
{
    private const string EMAIL_TEST = 'reset.test@sav.local';

    private PDO $pdo;
    private PasswordResetRepository $resets;
    private int $userId;

    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../.env');
        $this->pdo = Database::connection();
        $this->resets = new PasswordResetRepository($this->pdo);

        $this->pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => self::EMAIL_TEST]);
        $this->pdo->prepare(
            "INSERT INTO users (nom, prenom, email, password_hash, role, poste)
             VALUES ('Test', 'Reset', :email, :hash, 'technicien', 'sav')"
        )->execute(['email' => self::EMAIL_TEST, 'hash' => password_hash('ancien-mdp', PASSWORD_BCRYPT)]);
        $this->userId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $this->userId]);
    }

    public function testLeJetonEnClairNEstJamaisStockeEnBase(): void
    {
        $jeton = $this->resets->creerJeton($this->userId);

        $stmt = $this->pdo->prepare('SELECT token_hash FROM password_resets WHERE user_id = :id');
        $stmt->execute(['id' => $this->userId]);

        self::assertSame(64, strlen($jeton));
        self::assertSame(hash('sha256', $jeton), $stmt->fetchColumn());
    }

    public function testUnJetonValideRetrouveLUtilisateur(): void
    {
        $jeton = $this->resets->creerJeton($this->userId);

        self::assertSame($this->userId, $this->resets->trouverUtilisateurPourJeton($jeton));
    }

    public function testUnJetonInconnuOuMalFormeEstRefuse(): void
    {
        self::assertNull($this->resets->trouverUtilisateurPourJeton(str_repeat('a', 64)));
        self::assertNull($this->resets->trouverUtilisateurPourJeton('pas-un-jeton'));
        self::assertNull($this->resets->trouverUtilisateurPourJeton(''));
    }

    public function testUnJetonExpireEstRefuse(): void
    {
        $jeton = $this->resets->creerJeton($this->userId);
        $this->pdo->prepare('UPDATE password_resets SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE user_id = :id')
            ->execute(['id' => $this->userId]);

        self::assertNull($this->resets->trouverUtilisateurPourJeton($jeton));
        self::assertFalse($this->resets->reinitialiserMotDePasse($jeton, 'nouveau-mdp'));
    }

    public function testDemanderUnNouveauLienInvalideLePrecedent(): void
    {
        $ancien = $this->resets->creerJeton($this->userId);
        $nouveau = $this->resets->creerJeton($this->userId);

        self::assertNull($this->resets->trouverUtilisateurPourJeton($ancien));
        self::assertSame($this->userId, $this->resets->trouverUtilisateurPourJeton($nouveau));
    }

    public function testLaReinitialisationChangeLeMotDePasseEtConsommeLeJeton(): void
    {
        $jeton = $this->resets->creerJeton($this->userId);

        self::assertTrue($this->resets->reinitialiserMotDePasse($jeton, 'nouveau-mdp'));

        $user = new UserRepository($this->pdo)->findById($this->userId);
        self::assertNotNull($user);
        self::assertTrue(password_verify('nouveau-mdp', $user->passwordHash));
        self::assertFalse(password_verify('ancien-mdp', $user->passwordHash));

        // Usage unique : le meme lien ne peut pas resservir.
        self::assertNull($this->resets->trouverUtilisateurPourJeton($jeton));
        self::assertFalse($this->resets->reinitialiserMotDePasse($jeton, 'autre-mdp'));
    }
}
