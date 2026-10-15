<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Integration\Kbeauty\ContratClientMiseAJour;
use App\Integration\Kbeauty\MessageHorsContrat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\ClientGoldenRecord;

/** Tout message recu de RabbitMQ est verifie contre le contrat avant d'etre traite. */
final class ContratClientMiseAJourTest extends TestCase
{
    public function testAccepteUnMessageConformeAuContrat(): void
    {
        $goldenRecord = new ContratClientMiseAJour()->valider(ClientGoldenRecord::json());

        self::assertSame(7, $goldenRecord->sourceUserId);
        self::assertSame('jeanne.martin@exemple.test', $goldenRecord->email);
    }

    /** @return array<string, array{string}> */
    public static function messagesHorsContrat(): array
    {
        $sansEmail = json_decode(ClientGoldenRecord::json(), true);
        unset($sansEmail['email']);

        return [
            'JSON invalide' => ['{pas du json'],
            'pas un objet' => ['[1, 2]'],
            'champ obligatoire absent' => [json_encode($sansEmail)],
            'champ inconnu' => [ClientGoldenRecord::json(['telephone' => '0600000000'])],
            'autre type d\'evenement' => [ClientGoldenRecord::json(['eventType' => 'customer.updated'])],
            'identifiant negatif' => [ClientGoldenRecord::json(['sourceUserId' => -1])],
            'identifiant en texte' => [ClientGoldenRecord::json(['sourceUserId' => '7'])],
            'email invalide' => [ClientGoldenRecord::json(['email' => 'pas-un-email'])],
            'date invalide' => [ClientGoldenRecord::json(['updatedAt' => 'hier'])],
        ];
    }

    #[DataProvider('messagesHorsContrat')]
    public function testRejetteUnMessageHorsContrat(string $corps): void
    {
        $this->expectException(MessageHorsContrat::class);

        new ContratClientMiseAJour()->valider($corps);
    }
}
