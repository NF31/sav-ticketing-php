<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    public function testLesGuillemetsAutourDeLaValeurSontRetires(): void
    {
        self::assertSame('re_abc', Env::valeur('"re_abc"'));
        self::assertSame('re_abc', Env::valeur("'re_abc'"));
        self::assertSame('SAV <sav@exemple.fr>', Env::valeur(' "SAV <sav@exemple.fr>" '));
    }

    public function testLesValeursSansGuillemetsSontInchangees(): void
    {
        self::assertSame('re_abc', Env::valeur(' re_abc '));
        self::assertSame('SAV Ticketing <onboarding@resend.dev>', Env::valeur('SAV Ticketing <onboarding@resend.dev>'));
        self::assertSame('', Env::valeur(''));
    }

    public function testDesGuillemetsNonAppariesSontConserves(): void
    {
        self::assertSame('"re_abc', Env::valeur('"re_abc'));
        self::assertSame('"re_abc\'', Env::valeur('"re_abc\''));
        self::assertSame('"', Env::valeur('"'));
    }
}
