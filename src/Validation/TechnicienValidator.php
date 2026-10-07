<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enum\Poste;
use Closure;

/**
 * Validation du formulaire de creation d'un technicien. Classe sans acces
 * a la base (l'unicite de l'email est fournie par l'appelant) : testable
 * unitairement, sans serveur ni MariaDB.
 */
final class TechnicienValidator
{
    // Alignees sur les colonnes de la table users : au-dela, MariaDB (mode
    // strict) refuserait l'INSERT et la page planterait en erreur 500.
    public const int LONGUEUR_MAX_NOM = 100;
    public const int LONGUEUR_MAX_EMAIL = 255;

    // Lettres de toutes langues (accents compris), espaces, tirets et
    // apostrophes, en commencant par une lettre : "Jean-Pierre", "N'Diaye",
    // "Le Gall" passent, "Karim2" ou "<b>" non.
    private const string MOTIF_NOM = "/^\\p{L}[\\p{L}\\p{M}' -]*$/u";

    /**
     * Nettoie la saisie avant validation : espaces en trop retires des noms,
     * email en minuscules. Le mot de passe n'est jamais modifie (un espace
     * en debut ou fin peut etre volontaire).
     *
     * @param array<string, mixed> $saisie typiquement $_POST
     * @return array{nom: string, prenom: string, email: string, password: string, password_confirmation: string, poste: string}
     */
    public static function normaliser(array $saisie): array
    {
        $texte = static fn (string $cle): string => is_string($saisie[$cle] ?? null) ? $saisie[$cle] : '';
        $nom = static fn (string $valeur): string => (string) preg_replace('/\s+/u', ' ', trim($valeur));

        return [
            'nom' => $nom($texte('nom')),
            'prenom' => $nom($texte('prenom')),
            'email' => mb_strtolower(trim($texte('email'))),
            'password' => $texte('password'),
            'password_confirmation' => $texte('password_confirmation'),
            'poste' => trim($texte('poste')),
        ];
    }

    /**
     * @param array{nom: string, prenom: string, email: string, password: string, password_confirmation: string, poste: string} $donnees issues de normaliser()
     * @param Closure(string): bool $emailDejaPris
     * @return array<string, string> message d'erreur par champ, vide si tout est valide
     */
    public static function valider(array $donnees, Closure $emailDejaPris): array
    {
        $erreurs = [];

        foreach (['nom' => 'Le nom', 'prenom' => 'Le prenom'] as $champ => $libelle) {
            $erreur = self::erreurNom($donnees[$champ], $libelle);
            if ($erreur !== null) {
                $erreurs[$champ] = $erreur;
            }
        }

        $erreurEmail = self::erreurEmail($donnees['email'], $emailDejaPris);
        if ($erreurEmail !== null) {
            $erreurs['email'] = $erreurEmail;
        }

        $erreurMotDePasse = MotDePasse::erreur($donnees['password']);
        if ($erreurMotDePasse === null && $donnees['email'] !== '' && mb_strtolower($donnees['password']) === $donnees['email']) {
            $erreurMotDePasse = "Le mot de passe ne doit pas etre l'adresse email.";
        }
        if ($erreurMotDePasse !== null) {
            $erreurs['password'] = $erreurMotDePasse;
        } elseif ($donnees['password'] !== $donnees['password_confirmation']) {
            $erreurs['password_confirmation'] = 'Les deux mots de passe ne correspondent pas.';
        }

        if ($donnees['poste'] === '') {
            $erreurs['poste'] = 'Le poste est obligatoire.';
        } elseif (Poste::tryFrom($donnees['poste']) === null) {
            $erreurs['poste'] = 'Poste inconnu.';
        }

        return $erreurs;
    }

    private static function erreurNom(string $valeur, string $libelle): ?string
    {
        return match (true) {
            $valeur === '' => "{$libelle} est obligatoire.",
            mb_strlen($valeur) > self::LONGUEUR_MAX_NOM => "{$libelle} ne doit pas depasser " . self::LONGUEUR_MAX_NOM . ' caracteres.',
            preg_match(self::MOTIF_NOM, $valeur) !== 1 => "{$libelle} ne peut contenir que des lettres, espaces, tirets et apostrophes.",
            default => null,
        };
    }

    /** @param Closure(string): bool $emailDejaPris */
    private static function erreurEmail(string $email, Closure $emailDejaPris): ?string
    {
        return match (true) {
            $email === '' => "L'email est obligatoire.",
            mb_strlen($email) > self::LONGUEUR_MAX_EMAIL => "L'email ne doit pas depasser " . self::LONGUEUR_MAX_EMAIL . ' caracteres.',
            filter_var($email, FILTER_VALIDATE_EMAIL) === false => "L'email n'est pas valide (ex. prenom.nom@sav.local).",
            $emailDejaPris($email) => 'Un compte existe deja avec cet email.',
            default => null,
        };
    }
}
