<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Conteneur;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ConteneurTest extends TestCase
{
    public function testUnServiceEstCreeUneSeuleFois(): void
    {
        $conteneur = new Conteneur();
        $creations = 0;
        $conteneur->set('service', function () use (&$creations): stdClass {
            $creations++;

            return new stdClass();
        });

        self::assertSame($conteneur->get('service'), $conteneur->get('service'));
        self::assertSame(1, $creations);
    }

    public function testLaFabriqueRecoitLeConteneurPourSesDependances(): void
    {
        $conteneur = new Conteneur();
        $conteneur->set('dependance', static fn (): stdClass => new stdClass());
        $conteneur->set('service', static function (Conteneur $c): stdClass {
            $service = new stdClass();
            $service->dependance = $c->get('dependance');

            return $service;
        });

        self::assertSame($conteneur->get('dependance'), $conteneur->get('service')->dependance);
    }

    public function testRienNEstCreeAvantLaPremiereDemande(): void
    {
        $conteneur = new Conteneur();
        $conteneur->set('service', static fn (): never => throw new LogicException('ne doit pas etre appele'));

        self::assertTrue($conteneur->has('service'));
        self::assertFalse($conteneur->has('inconnu'));
    }

    public function testUnServiceNonDeclareEstUneErreurExplicite(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Service non declare dans config/services.php : inconnu.');

        new Conteneur()->get('inconnu');
    }

    public function testUneDependanceCirculaireEstDetectee(): void
    {
        $conteneur = new Conteneur();
        $conteneur->set('a', static fn (Conteneur $c): object => $c->get('b'));
        $conteneur->set('b', static fn (Conteneur $c): object => $c->get('a'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Dependance circulaire : a -> b -> a.');

        $conteneur->get('a');
    }

    public function testRedeclarerUnServiceRemplaceLInstanceExistante(): void
    {
        $conteneur = new Conteneur();
        $conteneur->set('service', static fn (): stdClass => (object) ['version' => 1]);
        $conteneur->get('service');

        $conteneur->set('service', static fn (): stdClass => (object) ['version' => 2]);

        self::assertSame(2, $conteneur->get('service')->version);
    }
}
