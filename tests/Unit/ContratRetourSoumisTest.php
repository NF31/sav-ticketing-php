<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Integration\Kbeauty\ContratRetourSoumis;
use App\Integration\Kbeauty\MessageHorsContrat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\RetourKbeauty;

/** Tout message recu de RabbitMQ est verifie contre le contrat avant d'etre traite. */
final class ContratRetourSoumisTest extends TestCase
{
    public function testAccepteUnMessageConformeAuContrat(): void
    {
        $retour = new ContratRetourSoumis()->valider(RetourKbeauty::json());

        self::assertSame(42, $retour->returnRequestId);
        self::assertSame('KB-2026-00042', $retour->order->number);
        self::assertNull($retour->items[1]->variantLabel);
    }

    /** @return array<string, array{string}> */
    public static function messagesHorsContrat(): array
    {
        $sansMotif = json_decode(RetourKbeauty::json(), true);
        unset($sansMotif['reason']);

        return [
            'JSON invalide' => ['{pas du json'],
            'pas un objet' => ['[1, 2]'],
            'champ obligatoire absent' => [json_encode($sansMotif)],
            'champ inconnu' => [RetourKbeauty::json(['adresse' => '1 rue de Paris'])],
            'autre type d\'evenement' => [RetourKbeauty::json(['eventType' => 'order.placed'])],
            'identifiant negatif' => [RetourKbeauty::json(['returnRequestId' => -1])],
            'identifiant en texte' => [RetourKbeauty::json(['returnRequestId' => '42'])],
            'email invalide' => [RetourKbeauty::json(['customer' => ['name' => 'X', 'email' => 'pas-un-email']])],
            'date invalide' => [RetourKbeauty::json(['occurredAt' => 'hier'])],
            'aucun article' => [RetourKbeauty::json(['items' => []])],
            'quantite nulle' => [RetourKbeauty::json(['items' => [['productName' => 'X', 'variantLabel' => null, 'quantity' => 0, 'amountCents' => 0]]])],
            'motif trop long' => [RetourKbeauty::json(['reason' => str_repeat('a', 1001)])],
        ];
    }

    #[DataProvider('messagesHorsContrat')]
    public function testRejetteUnMessageHorsContrat(string $corps): void
    {
        $this->expectException(MessageHorsContrat::class);

        new ContratRetourSoumis()->valider($corps);
    }
}
