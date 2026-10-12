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
 * oublie ou mal cable y est detecte par les tests et la CI, pas par une
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

    /** @return array<string, array{class-string}> tous les controleurs de src/Controller */
    public static function controleurs(): array
    {
        $controleurs = [];
        foreach (glob(__DIR__ . '/../../src/Controller/*Controller.php') ?: [] as $fichier) {
            $classe = 'App\\Controller\\' . basename($fichier, '.php');
            $controleurs[basename($fichier, '.php')] = [$classe];
        }

        return $controleurs;
    }

    /** @param class-string $classe */
    #[DataProvider('controleurs')]
    public function testChaqueControleurEstConstructible(string $classe): void
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
