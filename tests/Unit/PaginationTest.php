<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    /** @return array<string, array{?string, int}> */
    public static function pagesDemandeesProvider(): array
    {
        return [
            'absente' => [null, 1],
            'vide' => ['', 1],
            'normale' => ['2', 2],
            'non numerique' => ['abc', 1],
            'negative' => ['-2', 1],
            'zero' => ['0', 1],
            'decimale' => ['1.5', 1],
            'au-dela de la derniere' => ['999', 3],
            'depasse un entier' => ['99999999999999999999999', 3],
        ];
    }

    /** 45 elements par 20 : 3 pages. */
    #[DataProvider('pagesDemandeesProvider')]
    public function testPageDemandeeEstRameneeDansLesLimites(?string $demandee, int $attendue): void
    {
        self::assertSame($attendue, Pagination::depuis($demandee, 45, 20)->page);
    }

    public function testDecoupageEnPages(): void
    {
        $pagination = Pagination::depuis('2', 45, 20);

        self::assertSame(3, $pagination->nombrePages);
        self::assertSame(20, $pagination->offset());
        self::assertSame(21, $pagination->premier());
        self::assertSame(40, $pagination->dernier());
        self::assertTrue($pagination->aPrecedente());
        self::assertTrue($pagination->aSuivante());
    }

    public function testDernierePageIncomplete(): void
    {
        $pagination = Pagination::depuis('3', 45, 20);

        self::assertSame(40, $pagination->offset());
        self::assertSame(41, $pagination->premier());
        self::assertSame(45, $pagination->dernier());
        self::assertFalse($pagination->aSuivante());
    }

    public function testListeVideDonneUnePageSansElement(): void
    {
        $pagination = Pagination::depuis('4', 0);

        self::assertSame(1, $pagination->nombrePages);
        self::assertSame(1, $pagination->page);
        self::assertSame(0, $pagination->offset());
        self::assertSame(0, $pagination->premier());
        self::assertSame(0, $pagination->dernier());
        self::assertFalse($pagination->aPrecedente());
        self::assertFalse($pagination->aSuivante());
    }

    public function testTotalMultipleExactDeLaTailleDePage(): void
    {
        self::assertSame(2, Pagination::depuis(null, 40, 20)->nombrePages);
    }
}
