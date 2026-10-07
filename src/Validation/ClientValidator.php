<?php

declare(strict_types=1);

namespace App\Validation;

use Closure;

/**
 * Validation du formulaire de creation d'un client. Comme
 * TechnicienValidator : sans acces a la base, l'unicite de l'email est
 * fournie par l'appelant.
 */
final class ClientValidator
{
    // Alignees sur les colonnes de la table clients (MariaDB en mode strict
    // refuserait l'INSERT au-dela, d'ou une erreur 500).
    public const int LONGUEUR_MAX_NOM = 100;
    public const int LONGUEUR_MAX_EMAIL = 255;
    public const int LONGUEUR_MAX_RUE = 255;
    public const int LONGUEUR_MAX_VILLE = 100;

    // Telephone francais (0 + 9 chiffres) ou international au format E.164
    // (+ indicatif pays, 8 a 15 chiffres en tout). Separateurs retires avant.
    private const string MOTIF_TELEPHONE = '/^(0[1-9]\d{8}|\+[1-9]\d{7,14})$/';

    // Code postal francais : 5 chiffres (la Corse est en 20xxx).
    private const string MOTIF_CODE_POSTAL = '/^\d{5}$/';

    /**
     * @param array<string, mixed> $saisie typiquement $_POST
     * @return array{nom: string, prenom: string, email: string, telephone: string, adresse_rue: string, adresse_ville: string, adresse_code_postal: string}
     */
    public static function normaliser(array $saisie): array
    {
        return [
            'nom' => Regles::normaliserEspaces(Regles::texte($saisie, 'nom')),
            'prenom' => Regles::normaliserEspaces(Regles::texte($saisie, 'prenom')),
            'email' => Regles::normaliserEmail(Regles::texte($saisie, 'email')),
            // "06 01 02 03 04", "06.01.02.03.04", "+33 6 01 02 03 04" -> chiffres seuls.
            // "(0)" de "+33 (0)6..." retire d'abord : c'est une convention
            // d'ecriture, le 0 ne se compose pas apres l'indicatif.
            'telephone' => (string) preg_replace(['/\(0\)/', '/[\s.\-()]/'], '', Regles::texte($saisie, 'telephone')),
            'adresse_rue' => Regles::normaliserEspaces(Regles::texte($saisie, 'adresse_rue')),
            'adresse_ville' => Regles::normaliserEspaces(Regles::texte($saisie, 'adresse_ville')),
            'adresse_code_postal' => (string) preg_replace('/\s/', '', Regles::texte($saisie, 'adresse_code_postal')),
        ];
    }

    /**
     * @param array{nom: string, prenom: string, email: string, telephone: string, adresse_rue: string, adresse_ville: string, adresse_code_postal: string} $donnees issues de normaliser()
     * @param Closure(string): bool $emailDejaPris
     * @return array<string, string> message d'erreur par champ, vide si tout est valide
     */
    public static function valider(array $donnees, Closure $emailDejaPris): array
    {
        $erreurs = array_filter([
            'nom' => Regles::erreurNom($donnees['nom'], 'Le nom', self::LONGUEUR_MAX_NOM),
            'prenom' => Regles::erreurNom($donnees['prenom'], 'Le prenom', self::LONGUEUR_MAX_NOM),
            'email' => Regles::erreurEmail($donnees['email'], self::LONGUEUR_MAX_EMAIL, $emailDejaPris, 'Un client existe deja avec cet email.'),
            'telephone' => $donnees['telephone'] !== '' && preg_match(self::MOTIF_TELEPHONE, $donnees['telephone']) !== 1
                ? 'Le telephone doit contenir 10 chiffres commencant par 0 (ex. 06 01 02 03 04), ou etre au format international (ex. +33 6 01 02 03 04).'
                : null,
            'adresse_rue' => mb_strlen($donnees['adresse_rue']) > self::LONGUEUR_MAX_RUE
                ? 'La rue ne doit pas depasser ' . self::LONGUEUR_MAX_RUE . ' caracteres.'
                : null,
            'adresse_ville' => Regles::erreurNom($donnees['adresse_ville'], 'La ville', self::LONGUEUR_MAX_VILLE, obligatoire: false),
            'adresse_code_postal' => $donnees['adresse_code_postal'] !== '' && preg_match(self::MOTIF_CODE_POSTAL, $donnees['adresse_code_postal']) !== 1
                ? 'Le code postal doit contenir 5 chiffres (ex. 75001).'
                : null,
        ]);

        return $erreurs + self::erreursAdresseIncomplete($donnees);
    }

    /**
     * Adresse facultative, mais complete ou absente : une adresse partielle
     * (rue sans ville, ville sans code postal...) ne permet pas d'expedier
     * une piece ou un produit repare au client.
     *
     * @return array<string, string>
     */
    private static function erreursAdresseIncomplete(array $donnees): array
    {
        $champs = [
            'adresse_rue' => 'la rue',
            'adresse_ville' => 'la ville',
            'adresse_code_postal' => 'le code postal',
        ];
        $remplis = array_filter(array_intersect_key($donnees, $champs), static fn (string $v): bool => $v !== '');

        if ($remplis === [] || count($remplis) === count($champs)) {
            return [];
        }

        $erreurs = [];
        foreach (array_diff_key($champs, $remplis) as $champ => $libelle) {
            $erreurs[$champ] = "Adresse incomplete : renseignez aussi {$libelle} (ou laissez l'adresse entierement vide).";
        }

        return $erreurs;
    }
}
