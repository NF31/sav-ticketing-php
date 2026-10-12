<?php

declare(strict_types=1);

namespace App\Core;

use Uri\Rfc3986\Uri;

final class Request
{
    /**
     * @param array<string, string> $routeParams
     * @param array<string, mixed> $post corps de la requete ($_POST)
     * @param array<string, mixed> $query parametres d'URL ($_GET)
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $routeParams = [],
        private readonly array $post = [],
        private readonly array $query = [],
    ) {
    }

    /**
     * Seul endroit de l'application qui lit les superglobales de la requete :
     * les controleurs recoivent un objet Request, qu'un test peut construire
     * avec les donnees de son choix.
     */
    public static function fromGlobals(): self
    {
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            self::normaliserChemin($_SERVER['REQUEST_URI'] ?? '/'),
            post: $_POST,
            query: $_GET,
        );
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

    /**
     * IP de la connexion (REMOTE_ADDR), jamais X-Forwarded-For : cet
     * en-tete est fourni par le client, qui pourrait le changer a chaque
     * requete pour contourner une limitation par IP. Derriere un reverse
     * proxy, il faudrait le lire uniquement si REMOTE_ADDR est ce proxy.
     */
    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /** Champ du formulaire, espaces de debut et de fin retires ; chaine vide s'il est absent ou n'est pas du texte. */
    public function input(string $key, string $default = ''): string
    {
        $valeur = $this->post[$key] ?? $default;

        return is_string($valeur) ? trim($valeur) : $default;
    }

    /** Champ du formulaire tel quel (ex. mot de passe, ou un espace peut etre voulu). */
    public function brut(string $key): string
    {
        $valeur = $this->post[$key] ?? '';

        return is_string($valeur) ? $valeur : '';
    }

    public function intInput(string $key): ?int
    {
        $value = $this->post[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** @return array<string, mixed> tout le corps de la requete, pour les validateurs */
    public function donnees(): array
    {
        return $this->post;
    }

    /** Parametre d'URL (?cle=valeur) ; null s'il est absent ou n'est pas du texte. */
    public function query(string $key): ?string
    {
        $valeur = $this->query[$key] ?? null;

        return is_string($valeur) ? $valeur : null;
    }
}
