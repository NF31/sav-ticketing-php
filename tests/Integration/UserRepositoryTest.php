<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Env;
use App\Enum\Poste;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

final class UserRepositoryTest extends TestCase
{
    private UserRepository $users;

    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../.env');
        $this->users = new UserRepository(Database::connection());
    }

    public function testFindByEmailNeRenvoieQueLeCompteDemande(): void
    {
        $user = $this->users->findByEmail('karim.diallo@sav.local');

        self::assertNotNull($user);
        self::assertSame('Diallo', $user->nom);
        self::assertSame('technicien', $user->role->value);
    }

    public function testFindByEmailAvecEmailInconnuRenvoieNull(): void
    {
        self::assertNull($this->users->findByEmail('inconnu@sav.local'));
    }

    /**
     * L'auth ne doit jamais accepter un mot de passe en clair egal au hash
     * stocke : ca aurait ete le cas avec la comparaison directe faite dans
     * le projet original (getPasswords() puis comparaison string).
     */
    public function testLeHashDuMotDePasseNEstJamaisEgalAuMotDePasseEnClair(): void
    {
        $user = $this->users->findByEmail('admin@sav.local');

        self::assertNotNull($user);
        self::assertNotSame('password', $user->passwordHash);
        self::assertTrue(password_verify('password', $user->passwordHash));
    }

    public function testCreerTechnicienAvecEmailDejaPrisRenvoieNullSansException(): void
    {
        // Cas de deux creations simultanees : l'email passe la verification
        // emailExiste() mais la contrainte UNIQUE refuse l'INSERT.
        self::assertTrue($this->users->emailExiste('karim.diallo@sav.local'));
        self::assertNull($this->users->creerTechnicien('Test', 'Test', 'karim.diallo@sav.local', 'motdepasse1', Poste::Sav));
    }

    public function testCreerTechnicienHasheLeMotDePasse(): void
    {
        $email = 'creation.test@sav.local';
        Database::connection()->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);

        try {
            $id = $this->users->creerTechnicien('Test', 'Creation', $email, 'motdepasse1', Poste::Hotline);
            $user = $this->users->findById((int) $id);

            self::assertNotNull($user);
            self::assertSame('technicien', $user->role->value);
            self::assertSame(Poste::Hotline, $user->poste);
            self::assertTrue(password_verify('motdepasse1', $user->passwordHash));
        } finally {
            Database::connection()->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);
        }
    }
}
