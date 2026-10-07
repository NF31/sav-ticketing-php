<?php

declare(strict_types=1);

namespace App\Core\Mail;

/**
 * Mailer de dev (MAIL_MAILER=log) : les emails sont ecrits dans
 * var/mail.log au lieu d'etre envoyes, pour recuperer un lien de
 * reinitialisation sans compte chez un fournisseur.
 */
final class LogMailer implements Mailer
{
    public function __construct(private readonly string $fichier = __DIR__ . '/../../../var/mail.log')
    {
    }

    public function send(string $destinataire, string $sujet, string $corps): bool
    {
        $dossier = dirname($this->fichier);
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

        return file_put_contents($this->fichier, $email, FILE_APPEND | LOCK_EX) !== false;
    }
}
