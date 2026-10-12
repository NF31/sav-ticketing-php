<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Conteneur;
use App\Core\Env;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifie la vraie configuration (config/services.php) : un controleur
 * ou un service oublie ou mal cable y est detecte par les tests et la CI, pas par une
 * erreur 500 en production.
 */
final class ServicesTest extends TestCase
{
    private Conteneur $conteneur;

    protected function setUp(): void
    {
        Env::load(__DIR__ . '/../../.env');
        $this->conteneur = new Conteneur();
        (require __DIR__ . '/../../config/services.php')($this->conteneur);
    }

    /** @return array<string, array{class-string}> tous les controleurs de src/Controller et services de src/Service */
    public static function classesCablees(): array
    {
        $classes = [];
        foreach ([
            'Controller' => glob(__DIR__ . '/../../src/Controller/*Controller.php') ?: [],
            'Service' => glob(__DIR__ . '/../../src/Service/*Service.php') ?: [],
        ] as $dossier => $fichiers) {
            foreach ($fichiers as $fichier) {
                $classes[basename($fichier, '.php')] = ["App\\{$dossier}\\" . basename($fichier, '.php')];
            }
        }

        return $classes;
    }

    /** @param class-string $classe */
    #[DataProvider('classesCablees')]
    public function testChaqueControleurEtServiceEstConstructible(string $classe): void
    {
        self::assertInstanceOf($classe, $this->conteneur->get($classe));
    }

    public function testLesControleursPartagentLaMemeInstanceDAuth(): void
    {
        $auth = $this->conteneur->get(Auth::class);
        $lireAuth = static fn (object $controleur): Auth => (new \ReflectionProperty($controleur, 'auth'))->getValue($controleur);

        self::assertSame($auth, $lireAuth($this->conteneur->get(\App\Controller\TicketController::class)));
        self::assertSame($auth, $lireAuth($this->conteneur->get(\App\Controller\AuthController::class)));
    }
}
