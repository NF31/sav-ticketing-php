<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testLeJetonEstStableDansUneMemeSession(): void
    {
        $jeton = Csrf::jeton();

        self::assertSame(64, strlen($jeton));
        self::assertSame($jeton, Csrf::jeton());
    }

    public function testLeJetonDeLaSessionEstAccepte(): void
    {
        self::assertTrue(Csrf::valide(Csrf::jeton()));
    }

    public function testUnJetonAbsentFauxOuMalTypeEstRefuse(): void
    {
        Csrf::jeton();

        self::assertFalse(Csrf::valide(null));
        self::assertFalse(Csrf::valide(''));
        self::assertFalse(Csrf::valide(str_repeat('0', 64)));
        // $_POST['_csrf'][]=... donne un tableau : ne doit pas faire planter hash_equals().
        self::assertFalse(Csrf::valide(['x']));
    }

    public function testSansJetonEnSessionRienNEstAccepte(): void
    {
        self::assertFalse(Csrf::valide(''));
        self::assertFalse(Csrf::valide('nimporte-quoi'));
    }

    public function testRegenererInvalideLAncienJeton(): void
    {
        $ancien = Csrf::jeton();

        Csrf::regenerer();

        self::assertFalse(Csrf::valide($ancien));
        self::assertNotSame($ancien, Csrf::jeton());
    }
}
