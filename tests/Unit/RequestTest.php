<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function cheminsProvider(): array
    {
        return [
            'racine' => ['/', '/'],
            'slash final retire' => ['/tickets/', '/tickets'],
            'query string ignoree' => ['/tickets/12?statut=en_cours', '/tickets/12'],
            'slash final + query' => ['/tickets/12/?x=1', '/tickets/12'],
            'fragment ignore' => ['/clients#liste', '/clients'],
            'uri vide' => ['', '/'],
            'uri invalide ramenee a la racine' => ['http://[mal-formee', '/'],
        ];
    }

    #[DataProvider('cheminsProvider')]
    public function testNormaliserChemin(string $requestUri, string $attendu): void
    {
        self::assertSame($attendu, Request::normaliserChemin($requestUri));
    }

    public function testWithRouteParamsRetourneUneCopieSansModifierLOriginal(): void
    {
        $original = new Request('GET', '/tickets/12');

        $copie = $original->withRouteParams(['id' => '12']);

        self::assertNotSame($original, $copie);
        self::assertSame([], $original->routeParams);
        self::assertSame('12', $copie->param('id'));
        self::assertSame('GET', $copie->method);
        self::assertSame('/tickets/12', $copie->path);
    }
}
