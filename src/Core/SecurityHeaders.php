<?php

declare(strict_types=1);

namespace App\Core;

/**
 * En-tetes de securite envoyes sur toutes les reponses.
 */
final class SecurityHeaders
{
    /** @return array<string, string> */
    public static function liste(): array
    {
        $entetes = [
            // Aucun script ni style inline dans les templates : tout peut venir
            // de l'app elle-meme. Un script injecte (XSS qui aurait echappe a
            // e()) ne s'execute pas, et aucun site ne peut afficher l'app dans
            // une iframe (clickjacking) ni lui faire poster un formulaire
            // vers un autre domaine.
            'Content-Security-Policy' => "default-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'",
            // Meme protection anti-iframe pour les navigateurs sans CSP.
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            // Le jeton de reinitialisation est dans l'URL : il ne doit jamais
            // partir dans l'en-tete Referer vers un autre site.
            'Referrer-Policy' => 'same-origin',
        ];

        if (Session::enHttps()) {
            $entetes['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $entetes;
    }

    public static function envoyer(): void
    {
        foreach (self::liste() as $nom => $valeur) {
            header("{$nom}: {$valeur}");
        }
    }
}
