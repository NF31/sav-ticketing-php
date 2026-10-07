<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validation\ClientValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientValidatorTest extends TestCase
{
    /** @return array<string, string> */
    private static function saisieValide(array $surcharge = []): array
    {
        return array_merge([
            'nom' => 'Martin',
            'prenom' => 'Sophie',
            'email' => 'sophie.martin@example.com',
            'telephone' => '0601020304',
            'adresse_rue' => '12 rue de la Paix',
            'adresse_ville' => 'Toulouse',
            'adresse_code_postal' => '31000',
        ], $surcharge);
    }

    /** @return array<string, string> */
    private static function valider(array $saisie, bool $emailDejaPris = false): array
    {
        return ClientValidator::valider(
            ClientValidator::normaliser($saisie),
            static fn (string $email): bool => $emailDejaPris,
        );
    }

    public function testUneSaisieCompleteEstValide(): void
    {
        self::assertSame([], self::valider(self::saisieValide()));
    }

    public function testTelephoneEtAdresseSontFacultatifs(): void
    {
        $erreurs = self::valider(self::saisieValide([
            'telephone' => '',
            'adresse_rue' => '',
            'adresse_ville' => '',
            'adresse_code_postal' => '',
        ]));

        self::assertSame([], $erreurs);
    }

    public function testNormalisation(): void
    {
        $donnees = ClientValidator::normaliser(self::saisieValide([
            'email' => ' Sophie.MARTIN@Example.com ',
            'telephone' => '+33 (0)6.01-02 03 04',
            'adresse_ville' => '  Saint-Etienne ',
            'adresse_code_postal' => '42 000',
        ]));

        self::assertSame('sophie.martin@example.com', $donnees['email']);
        self::assertSame('+33601020304', $donnees['telephone']);
        self::assertSame('Saint-Etienne', $donnees['adresse_ville']);
        self::assertSame('42000', $donnees['adresse_code_postal']);
    }

    public function testEmailDejaPris(): void
    {
        $erreurs = self::valider(self::saisieValide(), emailDejaPris: true);

        self::assertSame('Un client existe deja avec cet email.', $erreurs['email']);
    }

    public function testNomTropLongEtCaracteresInterdits(): void
    {
        self::assertArrayHasKey('nom', self::valider(self::saisieValide(['nom' => str_repeat('a', 101)])));
        self::assertArrayHasKey('prenom', self::valider(self::saisieValide(['prenom' => 'Sophie3'])));
    }

    /** @return array<string, array{string}> */
    public static function telephonesAcceptes(): array
    {
        return [
            'mobile' => ['0601020304'],
            'avec espaces' => ['06 01 02 03 04'],
            'avec points' => ['01.23.45.67.89'],
            'international' => ['+33 6 01 02 03 04'],
            'etranger' => ['+32 470 12 34 56'],
        ];
    }

    #[DataProvider('telephonesAcceptes')]
    public function testTelephonesAcceptes(string $telephone): void
    {
        self::assertArrayNotHasKey('telephone', self::valider(self::saisieValide(['telephone' => $telephone])));
    }

    /** @return array<string, array{string}> */
    public static function telephonesRefuses(): array
    {
        return [
            'trop court' => ['060102'],
            'trop long' => ['060102030405'],
            'lettres' => ['06abcdefgh'],
            'commence par 00' => ['0001020304'],
            'plus suivi de 0' => ['+0601020304'],
        ];
    }

    #[DataProvider('telephonesRefuses')]
    public function testTelephonesRefuses(string $telephone): void
    {
        self::assertArrayHasKey('telephone', self::valider(self::saisieValide(['telephone' => $telephone])));
    }

    /** @return array<string, array{string}> */
    public static function codesPostauxRefuses(): array
    {
        return [
            '4 chiffres' => ['3100'],
            '6 chiffres' => ['310000'],
            'lettres' => ['ABCDE'],
        ];
    }

    #[DataProvider('codesPostauxRefuses')]
    public function testCodesPostauxRefuses(string $codePostal): void
    {
        self::assertArrayHasKey('adresse_code_postal', self::valider(self::saisieValide(['adresse_code_postal' => $codePostal])));
    }

    public function testVilleAvecChiffresRefusee(): void
    {
        self::assertArrayHasKey('adresse_ville', self::valider(self::saisieValide(['adresse_ville' => 'Paris 75'])));
    }

    public function testRueTropLongue(): void
    {
        self::assertArrayHasKey('adresse_rue', self::valider(self::saisieValide(['adresse_rue' => str_repeat('a', 256)])));
    }

    public function testAdresseIncompleteSignaleLesChampsManquants(): void
    {
        $erreurs = self::valider(self::saisieValide(['adresse_ville' => '', 'adresse_code_postal' => '']));

        self::assertArrayHasKey('adresse_ville', $erreurs);
        self::assertArrayHasKey('adresse_code_postal', $erreurs);
        self::assertArrayNotHasKey('adresse_rue', $erreurs);
    }

    public function testUneErreurDeFormatPrimeSurLAdresseIncomplete(): void
    {
        // Code postal mal forme + rue manquante : chaque champ garde son propre message.
        $erreurs = self::valider(self::saisieValide(['adresse_rue' => '', 'adresse_code_postal' => '123']));

        self::assertStringContainsString('5 chiffres', $erreurs['adresse_code_postal']);
        self::assertStringContainsString('Adresse incomplete', $erreurs['adresse_rue']);
    }

    public function testChampsAbsentsOuEnTableauNeFontPasPlanter(): void
    {
        $erreurs = self::valider(['nom' => ['x'], 'telephone' => ['y']]);

        self::assertArrayHasKey('nom', $erreurs);
        self::assertArrayHasKey('prenom', $erreurs);
        self::assertArrayHasKey('email', $erreurs);
        self::assertArrayNotHasKey('telephone', $erreurs);
    }
}
