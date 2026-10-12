<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Repository\PasswordResetRepository;
use App\Repository\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Sur un utilisateur cree puis supprime par le test : les comptes de demo
 * du seed ne sont jamais modifies.
 */
final class AuthTest extends TestCase
{
    private const string EMAIL_TEST = 'auth.test@sav.local';

    private PDO $pdo;
    private Auth $auth;
    private int $userId;

    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../.env');
        $_SESSION = [];
        $this->pdo = Database::connection();
        $this->auth = new Auth(new UserRepository($this->pdo));

        $this->pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => self::EMAIL_TEST]);
        $this->pdo->prepare(
            "INSERT INTO users (nom, prenom, email, password_hash, role, poste)
             VALUES ('Test', 'Auth', :email, :hash, 'technicien', 'sav')"
        )->execute(['email' => self::EMAIL_TEST, 'hash' => password_hash('bon-mdp-123', PASSWORD_BCRYPT)]);
        $this->userId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $this->userId]);
    }

    public function testConnexionAvecLeBonMotDePasse(): void
    {
        self::assertTrue($this->auth->attempt(self::EMAIL_TEST, 'bon-mdp-123'));
        self::assertSame($this->userId, $this->auth->user()?->id);
    }

    public function testConnexionRefuseeAvecMauvaisMotDePasseOuEmailInconnu(): void
    {
        self::assertFalse($this->auth->attempt(self::EMAIL_TEST, 'mauvais'));
        self::assertFalse($this->auth->attempt('inconnu@sav.local', 'bon-mdp-123'));
        self::assertNull($this->auth->user());
    }

    /**
     * Un email inconnu doit prendre a peu pres autant de temps qu'un mauvais
     * mot de passe (un bcrypt dans les deux cas). Sans le hash factice, il
     * repondrait en une fraction du temps (aucun bcrypt calcule).
     */
    public function testEmailInconnuPrendAutantDeTempsQuUnMauvaisMotDePasse(): void
    {
        $mesurer = function (string $email): float {
            $debut = hrtime(true);
            $this->auth->attempt($email, 'mauvais');

            return (hrtime(true) - $debut) / 1e6;
        };

        $mesurer(self::EMAIL_TEST); // chauffe (connexion, cache de requetes)
        $compteExistant = $mesurer(self::EMAIL_TEST);
        $compteInconnu = $mesurer('inconnu@sav.local');

        self::assertGreaterThan($compteExistant * 0.5, $compteInconnu);
    }

    public function testChangerLeMotDePasseDeconnecteLesSessionsOuvertes(): void
    {
        self::assertTrue($this->auth->attempt(self::EMAIL_TEST, 'bon-mdp-123'));

        $resets = new PasswordResetRepository($this->pdo);
        $resets->reinitialiserMotDePasse($resets->creerJeton($this->userId), 'nouveau-mdp-456');

        // Requete HTTP suivante de la session deja ouverte : nouvelle instance
        // d'Auth (une par requete), meme session.
        $requeteSuivante = new Auth(new UserRepository($this->pdo));

        self::assertNull($requeteSuivante->user());
        self::assertArrayNotHasKey('user_id', $_SESSION);
    }

    /**
     * user() est appele plusieurs fois par page (routeur, controleur,
     * layout) : une seule requete SQL pour l'utilisateur. Compte les
     * requetes preparees executees sur la connexion (compteur MariaDB).
     */
    public function testLUtilisateurNEstChargeQuUneFoisParInstance(): void
    {
        self::assertTrue($this->auth->attempt(self::EMAIL_TEST, 'bon-mdp-123'));
        $requete = new Auth(new UserRepository($this->pdo));

        // La lecture du compteur est elle-meme une requete preparee : son cout
        // est mesure puis retranche.
        $coutMesure = -$this->requetesExecutees() + $this->requetesExecutees();

        $avant = $this->requetesExecutees();
        $requete->check();
        $requete->isAdmin();
        $requete->user();
        $requete->user();

        self::assertSame(1, $this->requetesExecutees() - $avant - $coutMesure);
    }

    public function testLaDeconnexionVideLeCache(): void
    {
        self::assertTrue($this->auth->attempt(self::EMAIL_TEST, 'bon-mdp-123'));
        self::assertNotNull($this->auth->user());

        $this->auth->logout();

        self::assertNull($this->auth->user());
    }

    private function requetesExecutees(): int
    {
        return (int) $this->pdo->query("SHOW SESSION STATUS LIKE 'Com_stmt_execute'")->fetch()['Value'];
    }
}
