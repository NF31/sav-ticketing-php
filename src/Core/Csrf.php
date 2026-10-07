<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Protection CSRF par jeton de synchronisation : un jeton aleatoire par
 * session, ajoute en champ cache a chaque formulaire (csrf_champ()) et
 * verifie par le Router sur toute requete POST. Un site tiers peut faire
 * soumettre un formulaire au navigateur de la victime (avec ses cookies),
 * mais ne peut pas lire ce jeton, donc pas le joindre a la requete.
 */
final class Csrf
{
    public const string CHAMP = '_csrf';
    private const string SESSION_KEY = '_csrf_token';

    public static function jeton(): string
    {
        $jeton = Session::get(self::SESSION_KEY);

        if (!is_string($jeton)) {
            $jeton = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $jeton);
        }

        return $jeton;
    }

    public static function valide(mixed $jetonRecu): bool
    {
        $jetonSession = Session::get(self::SESSION_KEY);

        // hash_equals : comparaison en temps constant, qui ne laisse pas
        // deviner le jeton caractere par caractere au temps de reponse.
        return is_string($jetonSession) && is_string($jetonRecu) && hash_equals($jetonSession, $jetonRecu);
    }

    /** Nouveau jeton a la connexion, comme le nouvel identifiant de session. */
    public static function regenerer(): void
    {
        Session::remove(self::SESSION_KEY);
    }
}
