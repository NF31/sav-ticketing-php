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

    public function testDonneesDuFormulaire(): void
    {
        $request = new Request('POST', '/login', post: [
            'email' => '  awa@exemple.fr ',
            'password' => ' mdp avec espaces ',
            'technicien_id' => '12',
            'champ_tableau' => ['x'],
        ]);

        self::assertSame('awa@exemple.fr', $request->input('email'));
        // brut() ne retire rien : un espace peut faire partie d'un mot de passe.
        self::assertSame(' mdp avec espaces ', $request->brut('password'));
        self::assertSame(12, $request->intInput('technicien_id'));
        self::assertSame('', $request->input('champ_tableau'));
        self::assertSame('', $request->brut('absent'));
        self::assertNull($request->intInput('absent'));
        self::assertSame('awa@exemple.fr', trim($request->donnees()['email']));
    }

    public function testParametresDUrl(): void
    {
        $request = new Request('GET', '/tickets', query: ['statut' => 'en_cours', 'liste' => ['a']]);

        self::assertSame('en_cours', $request->query('statut'));
        self::assertNull($request->query('liste'));
        self::assertNull($request->query('absent'));
    }

    public function testWithRouteParamsConserveLesDonnees(): void
    {
        $request = new Request('POST', '/tickets/3/statut', post: ['statut' => 'termine'], query: ['x' => '1']);

        $copie = $request->withRouteParams(['id' => '3']);

        self::assertSame('termine', $copie->input('statut'));
        self::assertSame('1', $copie->query('x'));
    }
}
