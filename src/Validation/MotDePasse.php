<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Regle de mot de passe commune a la creation d'un technicien et a la
 * reinitialisation.
 */
final class MotDePasse
{
    public const int LONGUEUR_MIN = 8;

    /**
     * bcrypt ignore silencieusement tout ce qui depasse 72 octets : deux
     * mots de passe qui ne different qu'apres le 72e octet seraient tous
     * les deux acceptes. On refuse donc plutot que de tronquer en cachette.
     * En octets et pas en caracteres : un caractere accentue en prend 2.
     */
    public const int LONGUEUR_MAX_OCTETS = 72;

    /** @return string|null le message d'erreur, null si le mot de passe est acceptable */
    public static function erreur(string $motDePasse): ?string
    {
        return match (true) {
            $motDePasse === '' => 'Le mot de passe est obligatoire.',
            mb_strlen($motDePasse) < self::LONGUEUR_MIN => 'Le mot de passe doit contenir au moins ' . self::LONGUEUR_MIN . ' caracteres.',
            strlen($motDePasse) > self::LONGUEUR_MAX_OCTETS => 'Le mot de passe est trop long (' . self::LONGUEUR_MAX_OCTETS . ' octets maximum, un caractere accentue en compte 2).',
            default => null,
        };
    }
}
