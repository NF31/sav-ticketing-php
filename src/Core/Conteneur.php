<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use LogicException;

/**
 * Conteneur d'injection de dependances minimal : chaque service est
 * declare par une fabrique (config/services.php), cree a la premiere
 * demande puis reutilise pendant toute la requete.
 *
 * Cablage explicite plutot qu'automatique (autowiring par reflexion comme
 * dans Symfony ou Laravel) : chaque dependance se lit dans
 * config/services.php, sans magie a deviner.
 */
final class Conteneur
{
    /** @var array<string, Closure(self): object> */
    private array $fabriques = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, true> services en cours de creation, pour detecter les cycles */
    private array $enCours = [];

    /** @param Closure(self): object $fabrique */
    public function set(string $id, Closure $fabrique): void
    {
        $this->fabriques[$id] = $fabrique;
        unset($this->instances[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->fabriques[$id]);
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : object)
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->fabriques[$id])) {
            throw new LogicException("Service non declare dans config/services.php : {$id}.");
        }

        // A dependant de B qui depend de A : sans cette verification, la
        // creation bouclerait jusqu'a epuiser la memoire.
        if (isset($this->enCours[$id])) {
            $chaine = implode(' -> ', [...array_keys($this->enCours), $id]);

            throw new LogicException("Dependance circulaire : {$chaine}.");
        }

        $this->enCours[$id] = true;

        try {
            $instance = ($this->fabriques[$id])($this);
        } finally {
            unset($this->enCours[$id]);
        }

        return $this->instances[$id] = $instance;
    }
}
