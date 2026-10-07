<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\ApresReponse;
use App\Core\Mail\LogMailer;
use App\Core\Mail\MailerFactory;
use App\Core\Mail\ResendMailer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MailTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $envInitial;
    private string $journal;
    private string|false $journalInitial;

    protected function setUp(): void
    {
        $this->envInitial = $_ENV;
        // error_log() redirige vers un fichier temporaire pour pouvoir verifier
        // ce qui est journalise (et ne pas polluer la sortie de PHPUnit).
        $this->journal = (string) tempnam(sys_get_temp_dir(), 'journal');
        $this->journalInitial = ini_set('error_log', $this->journal);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envInitial;
        ini_set('error_log', (string) $this->journalInitial);
        @unlink($this->journal);
    }

    public function testResendEnvoieLaBonneRequete(): void
    {
        $requete = null;
        $mailer = new ResendMailer('re_cle_test', 'SAV <sav@exemple.fr>', function (string $url, array $entetes, string $json) use (&$requete): array {
            $requete = compact('url', 'entetes', 'json');

            return ['statut' => 200, 'corps' => '{"id":"abc"}'];
        });

        self::assertTrue($mailer->send('awa@exemple.fr', 'Sujet é', "Ligne 1\nLigne 2"));
        self::assertSame('https://api.resend.com/emails', $requete['url']);
        self::assertSame('Bearer re_cle_test', $requete['entetes']['Authorization']);
        self::assertSame([
            'from' => 'SAV <sav@exemple.fr>',
            'to' => ['awa@exemple.fr'],
            'subject' => 'Sujet é',
            'text' => "Ligne 1\nLigne 2",
        ], json_decode($requete['json'], true));
    }

    public function testUnEchecResendRenvoieFalseEtEstJournaliseSansCleNiDestinataire(): void
    {
        $mailer = new ResendMailer('re_cle_secrete', 'SAV <sav@exemple.fr>', static fn (): array => [
            'statut' => 403,
            'corps' => '{"message":"The exemple.fr domain is not verified."}',
        ]);

        self::assertFalse($mailer->send('victime@exemple.fr', 'Sujet', 'Corps'));

        $journal = (string) file_get_contents($this->journal);
        self::assertStringContainsString('HTTP 403', $journal);
        self::assertStringContainsString('domain is not verified', $journal);
        self::assertStringNotContainsString('re_cle_secrete', $journal);
        self::assertStringNotContainsString('victime@exemple.fr', $journal);
    }

    public function testUneErreurReseauRenvoieFalse(): void
    {
        $mailer = new ResendMailer('re_x', 'SAV <sav@exemple.fr>', static fn (): array => ['statut' => 0, 'corps' => 'erreur curl : timeout']);

        self::assertFalse($mailer->send('a@exemple.fr', 'S', 'C'));
        self::assertStringContainsString('HTTP 0', (string) file_get_contents($this->journal));
    }

    public function testLogMailerEcritLEmail(): void
    {
        $fichier = sys_get_temp_dir() . '/mail-test-' . bin2hex(random_bytes(4)) . '.log';

        try {
            self::assertTrue(new LogMailer($fichier)->send('a@exemple.fr', 'Sujet test', 'Corps test'));
            $contenu = (string) file_get_contents($fichier);
            self::assertStringContainsString('A : a@exemple.fr', $contenu);
            self::assertStringContainsString('Corps test', $contenu);
        } finally {
            @unlink($fichier);
        }
    }

    public function testLaFactoryChoisitLeDriver(): void
    {
        $_ENV['MAIL_DRIVER'] = 'log';
        self::assertInstanceOf(LogMailer::class, MailerFactory::depuisEnv());

        $_ENV['MAIL_DRIVER'] = 'resend';
        $_ENV['RESEND_API_KEY'] = 're_x';
        $_ENV['MAIL_FROM'] = 'SAV <sav@exemple.fr>';
        self::assertInstanceOf(ResendMailer::class, MailerFactory::depuisEnv());
    }

    public function testResendSansCleEstUneErreurDeConfiguration(): void
    {
        $_ENV['MAIL_DRIVER'] = 'resend';
        $_ENV['RESEND_API_KEY'] = '';
        $_ENV['MAIL_FROM'] = 'SAV <sav@exemple.fr>';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('RESEND_API_KEY');
        MailerFactory::depuisEnv();
    }

    public function testDriverInconnuEstUneErreurDeConfiguration(): void
    {
        $_ENV['MAIL_DRIVER'] = 'smtp';

        $this->expectException(RuntimeException::class);
        MailerFactory::depuisEnv();
    }

    public function testLesTachesApresReponseSontIsolees(): void
    {
        $executees = [];
        ApresReponse::ajouter(function () use (&$executees): void { $executees[] = 1; });
        ApresReponse::ajouter(static function (): never { throw new RuntimeException('envoi impossible'); });
        ApresReponse::ajouter(function () use (&$executees): void { $executees[] = 3; });

        ApresReponse::executerTaches();

        // La tache en echec n'empeche pas la suivante, et l'erreur est journalisee.
        self::assertSame([1, 3], $executees);
        self::assertStringContainsString('envoi impossible', (string) file_get_contents($this->journal));

        // Les taches ne sont executees qu'une fois.
        ApresReponse::executerTaches();
        self::assertSame([1, 3], $executees);
    }
}
