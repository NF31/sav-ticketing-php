<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enum\TicketStatut;
use PHPUnit\Framework\TestCase;

final class TicketStatutTest extends TestCase
{
    public function testNouveauPeutAllerVersEnCoursOuAttentePiece(): void
    {
        $transitions = TicketStatut::Nouveau->transitionsAutorisees();

        self::assertContains(TicketStatut::EnCours, $transitions);
        self::assertContains(TicketStatut::EnAttentePiece, $transitions);
        self::assertNotContains(TicketStatut::Termine, $transitions);
    }

    public function testTermineEstUnEtatFinalSansTransition(): void
    {
        self::assertSame([], TicketStatut::Termine->transitionsAutorisees());
    }

    public function testEnAttentePieceNeRepartQueVersEnCours(): void
    {
        self::assertSame([TicketStatut::EnCours], TicketStatut::EnAttentePiece->transitionsAutorisees());
    }

    public function testToutesLesValeursOntUnLabel(): void
    {
        foreach (TicketStatut::cases() as $statut) {
            self::assertNotSame('', $statut->label());
        }
    }
}
