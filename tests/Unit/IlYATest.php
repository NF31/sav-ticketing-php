<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IlYATest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function ecartsProvider(): array
    {
        return [
            'quelques secondes' => ['-30 seconds', "a l'instant"],
            'minutes' => ['-5 minutes', 'il y a 5 min'],
            'juste sous une heure' => ['-59 minutes', 'il y a 59 min'],
            'heures' => ['-3 hours', 'il y a 3 h'],
            'jours' => ['-2 days', 'il y a 2 j'],
            'au-dela d une semaine' => ['-10 days', 'le 02/10/2026'],
        ];
    }

    #[DataProvider('ecartsProvider')]
    public function testDateRelative(string $ecart, string $attendu): void
    {
        $maintenant = new DateTimeImmutable('2026-10-12 15:00:00');

        self::assertSame($attendu, il_y_a($maintenant->modify($ecart), $maintenant));
    }
}
