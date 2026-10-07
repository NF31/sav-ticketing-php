<?php

declare(strict_types=1);

/**
 * Echappement HTML par defaut pour tout affichage venant de la base ou
 * d'une saisie utilisateur dans les templates. Dans le projet original,
 * les valeurs etaient injectees directement dans le HTML sans passer par
 * htmlspecialchars() nulle part : faille XSS stockee (ex. un nom de
 * technicien contenant du HTML se serait execute tel quel).
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Champ cache du jeton CSRF, a placer dans chaque formulaire POST : le
 * Router refuse toute requete POST sans jeton valide.
 */
function csrf_champ(): string
{
    return '<input type="hidden" name="' . \App\Core\Csrf::CHAMP . '" value="' . e(\App\Core\Csrf::jeton()) . '">';
}

/**
 * Attributs d'accessibilite d'un champ en erreur (bordure rouge en CSS,
 * message lu par les lecteurs d'ecran), chaine vide sinon.
 *
 * @param array<string, string> $erreurs
 */
function champ_invalide(array $erreurs, string $champ): string
{
    return isset($erreurs[$champ]) ? ' aria-invalid="true" aria-describedby="erreur-' . e($champ) . '"' : '';
}

/**
 * Message d'erreur a afficher sous un champ, chaine vide sinon.
 *
 * @param array<string, string> $erreurs
 */
function champ_erreur(array $erreurs, string $champ): string
{
    return isset($erreurs[$champ])
        ? '<p class="field-error" id="erreur-' . e($champ) . '">' . e($erreurs[$champ]) . '</p>'
        : '';
}
