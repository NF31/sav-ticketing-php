<?php

declare(strict_types=1);

namespace App\Validation;

use Closure;

/**
 * Regles de validation communes aux formulaires (technicien, client).
 * Chaque methode renvoie le message d'erreur, ou null si la valeur est
 * acceptable.
 */
final class Regles
{
    // Lettres de toutes langues (accents compris), espaces, tirets et
    // apostrophes, en commencant par une lettre : "Jean-Pierre", "N'Diaye",
    // "Saint-Etienne" passent, "Karim2" ou "<b>" non.
    private const string MOTIF_NOM = "/^\\p{L}[\\p{L}\\p{M}' -]*$/u";

    /** @param array<string, mixed> $saisie */
    public static function texte(array $saisie, string $cle): string
    {
        return is_string($saisie[$cle] ?? null) ? $saisie[$cle] : '';
    }

    /** Espaces de debut/fin retires et espaces multiples ramenes a un seul. */
    public static function normaliserEspaces(string $valeur): string
    {
        return (string) preg_replace('/\s+/u', ' ', trim($valeur));
    }

    public static function normaliserEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function erreurNom(string $valeur, string $libelle, int $longueurMax, bool $obligatoire = true): ?string
    {
        return match (true) {
            $valeur === '' => $obligatoire ? "{$libelle} est obligatoire." : null,
            mb_strlen($valeur) > $longueurMax => "{$libelle} ne doit pas depasser {$longueurMax} caracteres.",
            preg_match(self::MOTIF_NOM, $valeur) !== 1 => "{$libelle} ne peut contenir que des lettres, espaces, tirets et apostrophes.",
            default => null,
        };
    }

    /** @param Closure(string): bool $emailDejaPris */
    public static function erreurEmail(string $email, int $longueurMax, Closure $emailDejaPris, string $messageDejaPris): ?string
    {
        return match (true) {
            $email === '' => "L'email est obligatoire.",
            mb_strlen($email) > $longueurMax => "L'email ne doit pas depasser {$longueurMax} caracteres.",
            filter_var($email, FILTER_VALIDATE_EMAIL) === false => "L'email n'est pas valide (ex. prenom.nom@exemple.fr).",
            $emailDejaPris($email) => $messageDejaPris,
            default => null,
        };
    }
}
