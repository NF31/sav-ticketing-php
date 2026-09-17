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
