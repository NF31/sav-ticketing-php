<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Envoi d'email minimal : pas de serveur SMTP configure dans ce projet,
 * les emails sont donc ecrits dans var/mail.log (lisible en dev pour
 * recuperer un lien de reinitialisation). Brancher un vrai transport
 * (SMTP, API d'un fournisseur) se ferait ici sans toucher aux controleurs.
 */
final class Mailer
{
    private const string LOG_FILE = __DIR__ . '/../../var/mail.log';

    public function send(string $destinataire, string $sujet, string $corps): void
    {
        $dossier = dirname(self::LOG_FILE);
        if (!is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }

        $email = sprintf(
            "==== %s ====\nA : %s\nSujet : %s\n\n%s\n\n",
            date('Y-m-d H:i:s'),
            $destinataire,
            $sujet,
            $corps,
        );

        file_put_contents(self::LOG_FILE, $email, FILE_APPEND | LOCK_EX);
    }
}
