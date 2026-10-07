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
        return [
            'nom' => Regles::normaliserEspaces(Regles::texte($saisie, 'nom')),
            'prenom' => Regles::normaliserEspaces(Regles::texte($saisie, 'prenom')),
            'email' => Regles::normaliserEmail(Regles::texte($saisie, 'email')),
            'password' => Regles::texte($saisie, 'password'),
            'password_confirmation' => Regles::texte($saisie, 'password_confirmation'),
            'poste' => trim(Regles::texte($saisie, 'poste')),
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
            $erreur = Regles::erreurNom($donnees[$champ], $libelle, self::LONGUEUR_MAX_NOM);
            if ($erreur !== null) {
                $erreurs[$champ] = $erreur;
            }
        }

        $erreurEmail = Regles::erreurEmail($donnees['email'], self::LONGUEUR_MAX_EMAIL, $emailDejaPris, 'Un compte existe deja avec cet email.');
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
}
