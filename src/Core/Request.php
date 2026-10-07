<?php

declare(strict_types=1);

namespace App\Core;

use Uri\Rfc3986\Uri;

final class Request
{
    /** @param array<string, string> $routeParams */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $routeParams = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        return new self($method, self::normaliserChemin($_SERVER['REQUEST_URI'] ?? '/'));
    }

    /**
     * Extrait le chemin d'une URI de requete ("/tickets/12/?x=1" -> "/tickets/12").
     * Parseur URI natif de PHP 8.5 (conforme RFC 3986) plutot que parse_url(),
     * qui n'applique aucune norme et accepte des URI malformees ; une URI
     * invalide donne null, ramene ici sur "/".
     */
    public static function normaliserChemin(string $requestUri): string
    {
        return $requestUri
            |> Uri::parse(...)
            |> (fn (?Uri $uri): string => $uri?->getPath() ?: '/')
            |> (fn (string $path): string => rtrim($path, '/') ?: '/');
    }

    /**
     * clone-with (PHP 8.5) : copie l'objet en ne remplacant que routeParams,
     * sans re-lister tous les arguments du constructeur. #[\NoDiscard] leve
     * un warning si l'appelant ignore le retour en croyant avoir modifie
     * $this (l'objet est immuable, rien n'est modifie en place).
     */
    #[\NoDiscard('Request est immuable : withRouteParams() retourne une nouvelle instance.')]
    public function withRouteParams(array $params): self
    {
        return clone($this, ['routeParams' => $params]);
    }

    public function param(string $key): string
    {
        return $this->routeParams[$key] ?? '';
    }

    public function input(string $key, string $default = ''): string
    {
        return trim((string) ($_POST[$key] ?? $default));
    }

    public function intInput(string $key): ?int
    {
        $value = $_POST[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
