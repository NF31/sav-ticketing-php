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

/**
 * Icone SVG inline (trait, couleur heritee du texte). Inline plutot qu'une
 * police d'icones ou un CDN : la CSP n'autorise que les ressources du site
 * lui-meme, et un SVG inline ne fait aucune requete.
 */
function icone(string $nom, string $classe = 'icon'): string
{
    $traces = [
        'ticket' => '<path d="M4 6h16v3a2 2 0 0 0 0 4v3H4v-3a2 2 0 0 0 0-4z"/><path d="M14 6v2m0 3v2m0 3v2"/>',
        'clients' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20v-1a4.5 4.5 0 0 1 4.5-4.5h4a4.5 4.5 0 0 1 4.5 4.5v1"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7"/><path d="M21.5 20v-1a4.5 4.5 0 0 0-3-4.24"/>',
        'articles' => '<path d="M12 3 3 8l9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'equipe' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16a3 3 0 0 1 6 0"/><path d="M15 10h3m-3 4h3"/>',
        'deconnexion' => '<path d="M9 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h3"/><path d="m16 16 4-4-4-4"/><path d="M20 12H10"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'retour' => '<path d="M19 12H5"/><path d="m11 18-6-6 6-6"/>',
        'logo' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'vide' => '<path d="M3 13h5l2 3h4l2-3h5"/><path d="M5.5 5h13l2.5 8v5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-5z"/>',
    ];

    return '<svg class="' . e($classe) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . ($traces[$nom] ?? '') . '</svg>';
}

/** "Karim Diallo" -> "KD", pour les avatars. */
function initiales(string $nomComplet): string
{
    $mots = preg_split('/[\s-]+/u', trim($nomComplet), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $lettres = array_map(static fn (string $mot): string => mb_substr($mot, 0, 1), array_slice($mots, 0, 2));

    return mb_strtoupper(implode('', $lettres));
}
