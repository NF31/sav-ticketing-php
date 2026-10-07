<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validation\MotDePasse;
use App\Validation\TechnicienValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TechnicienValidatorTest extends TestCase
{
    /** @return array<string, string> */
    private static function saisieValide(array $surcharge = []): array
    {
        return array_merge([
            'nom' => 'Diallo',
            'prenom' => 'Awa',
            'email' => 'awa.diallo@sav.local',
            'password' => 'motdepasse1',
            'password_confirmation' => 'motdepasse1',
            'poste' => 'sav',
        ], $surcharge);
    }

    /** @return array<string, string> */
    private static function valider(array $saisie, bool $emailDejaPris = false): array
    {
        return TechnicienValidator::valider(
            TechnicienValidator::normaliser($saisie),
            static fn (string $email): bool => $emailDejaPris,
        );
    }

    public function testUneSaisieCompleteEstValide(): void
    {
        self::assertSame([], self::valider(self::saisieValide()));
    }

    public function testNormalisationDesNomsEtDeLEmail(): void
    {
        $donnees = TechnicienValidator::normaliser(self::saisieValide([
            'nom' => "  Le   Gall \t",
            'email' => '  Awa.DIALLO@Sav.Local ',
            'password' => ' mdp avec espaces ',
        ]));

        self::assertSame('Le Gall', $donnees['nom']);
        self::assertSame('awa.diallo@sav.local', $donnees['email']);
        // Le mot de passe n'est jamais retouche.
        self::assertSame(' mdp avec espaces ', $donnees['password']);
    }

    public function testChampsAbsentsOuEnTableauNeFontPasPlanter(): void
    {
        $erreurs = self::valider(['nom' => ['x'], 'prenom' => null]);

        self::assertArrayHasKey('nom', $erreurs);
        self::assertArrayHasKey('prenom', $erreurs);
        self::assertArrayHasKey('email', $erreurs);
        self::assertArrayHasKey('password', $erreurs);
        self::assertArrayHasKey('poste', $erreurs);
    }

    /** @return array<string, array{string}> */
    public static function nomsAcceptes(): array
    {
        return [
            'accents' => ['Hélène'],
            'tiret' => ['Jean-Pierre'],
            'apostrophe' => ["N'Diaye"],
            'espace' => ['Le Gall'],
            'alphabet non latin' => ['Nguyễn'],
        ];
    }

    #[DataProvider('nomsAcceptes')]
    public function testNomsAcceptes(string $nom): void
    {
        self::assertArrayNotHasKey('nom', self::valider(self::saisieValide(['nom' => $nom])));
    }

    /** @return array<string, array{string}> */
    public static function nomsRefuses(): array
    {
        return [
            'vide' => [''],
            'que des espaces' => ['   '],
            'chiffre' => ['Karim2'],
            'html' => ['<b>Diallo</b>'],
            'commence par un tiret' => ['-Diallo'],
            'trop long' => [str_repeat('a', TechnicienValidator::LONGUEUR_MAX_NOM + 1)],
        ];
    }

    #[DataProvider('nomsRefuses')]
    public function testNomsRefuses(string $nom): void
    {
        self::assertArrayHasKey('nom', self::valider(self::saisieValide(['nom' => $nom])));
    }

    public function testLongueurMaxDuNomCompteeEnCaracteresPasEnOctets(): void
    {
        $nom = str_repeat('é', TechnicienValidator::LONGUEUR_MAX_NOM);

        self::assertArrayNotHasKey('nom', self::valider(self::saisieValide(['nom' => $nom])));
    }

    /** @return array<string, array{string}> */
    public static function emailsRefuses(): array
    {
        return [
            'vide' => [''],
            'sans arobase' => ['awa.sav.local'],
            'sans domaine' => ['awa@'],
            'trop long' => [str_repeat('a', 250) . '@sav.local'],
        ];
    }

    #[DataProvider('emailsRefuses')]
    public function testEmailsRefuses(string $email): void
    {
        self::assertArrayHasKey('email', self::valider(self::saisieValide(['email' => $email])));
    }

    public function testEmailDejaPris(): void
    {
        $erreurs = self::valider(self::saisieValide(), emailDejaPris: true);

        self::assertSame('Un compte existe deja avec cet email.', $erreurs['email']);
    }

    public function testMotDePasseTropCourt(): void
    {
        $erreurs = self::valider(self::saisieValide(['password' => 'court1', 'password_confirmation' => 'court1']));

        self::assertArrayHasKey('password', $erreurs);
    }

    public function testMotDePasseAuDelaDe72OctetsRefuse(): void
    {
        // 37 "é" = 74 octets mais 37 caracteres : refuse quand meme.
        $long = str_repeat('é', 37);

        self::assertNull(MotDePasse::erreur(str_repeat('a', 72)));
        self::assertNotNull(MotDePasse::erreur(str_repeat('a', 73)));
        self::assertNotNull(MotDePasse::erreur($long));
    }

    public function testMotDePasseEgalALEmailRefuse(): void
    {
        $email = 'awa.diallo@sav.local';
        $erreurs = self::valider(self::saisieValide(['password' => $email, 'password_confirmation' => $email]));

        self::assertArrayHasKey('password', $erreurs);
    }

    public function testConfirmationDifferente(): void
    {
        $erreurs = self::valider(self::saisieValide(['password_confirmation' => 'autre-mdp1']));

        self::assertArrayHasKey('password_confirmation', $erreurs);
        self::assertArrayNotHasKey('password', $erreurs);
    }

    public function testPosteObligatoireEtConnu(): void
    {
        self::assertArrayHasKey('poste', self::valider(self::saisieValide(['poste' => ''])));
        self::assertArrayHasKey('poste', self::valider(self::saisieValide(['poste' => 'directeur'])));
        self::assertArrayNotHasKey('poste', self::valider(self::saisieValide(['poste' => 'hotline'])));
    }
}
