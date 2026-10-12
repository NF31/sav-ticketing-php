<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repository\TicketRepository;
use App\Validation\TicketValidator;
use PHPUnit\Framework\TestCase;

final class TicketValidatorTest extends TestCase
{
    /** @return array<string, string> */
    private static function valider(array $saisie): array
    {
        return TicketValidator::valider(
            TicketValidator::normaliser($saisie),
            // Seule la commande 1 existe dans ces tests.
            static fn (int $id): bool => $id === 1,
        );
    }

    public function testUneSaisieValideSansDescription(): void
    {
        self::assertSame([], self::valider(['commande_id' => '1', 'type' => 'reparation']));
    }

    public function testCommandeObligatoireEtExistante(): void
    {
        self::assertSame('La commande est obligatoire.', self::valider(['type' => 'reparation'])['commande_id']);
        self::assertSame("Cette commande n'existe pas.", self::valider(['commande_id' => '9999', 'type' => 'reparation'])['commande_id']);
        self::assertSame("Cette commande n'existe pas.", self::valider(['commande_id' => '1abc', 'type' => 'reparation'])['commande_id']);
        self::assertSame("Cette commande n'existe pas.", self::valider(['commande_id' => '-1', 'type' => 'reparation'])['commande_id']);
    }

    public function testTypeObligatoireEtConnu(): void
    {
        self::assertArrayHasKey('type', self::valider(['commande_id' => '1']));
        self::assertSame('Type de ticket inconnu.', self::valider(['commande_id' => '1', 'type' => 'urgent'])['type']);
    }

    /** Les retours viennent uniquement de la boutique (RabbitMQ), pas du formulaire. */
    public function testUnRetourNePeutPasEtreSaisiALaMain(): void
    {
        self::assertSame('Type de ticket inconnu.', self::valider(['commande_id' => '1', 'type' => 'retour'])['type']);
    }

    public function testDescriptionLimiteeEnCaracteres(): void
    {
        $max = TicketValidator::LONGUEUR_MAX_DESCRIPTION;

        self::assertArrayNotHasKey('description', self::valider(['commande_id' => '1', 'type' => 'hotline', 'description' => str_repeat('é', $max)]));
        self::assertArrayHasKey('description', self::valider(['commande_id' => '1', 'type' => 'hotline', 'description' => str_repeat('a', $max + 1)]));
    }

    public function testDescriptionGardeSesRetoursALaLigne(): void
    {
        $donnees = TicketValidator::normaliser(['description' => "  Ligne 1\r\nLigne 2\n  "]);

        self::assertSame("Ligne 1\nLigne 2", $donnees['description']);
    }

    public function testLeCodeDuTicketTientDansLaColonne(): void
    {
        // tickets.code est un VARCHAR(10).
        self::assertSame('TCK-0042', TicketRepository::code(42));
        self::assertSame(10, strlen(TicketRepository::code(999999)));
    }
}
