<?php

declare(strict_types=1);

namespace App\Core\Mail;

/**
 * Envoi d'un email texte. Deux implementations : LogMailer (dev, ecrit
 * dans var/mail.log) et ResendMailer (envoi reel via l'API Resend),
 * choisies par MAIL_MAILER dans .env (voir MailerFactory). Les
 * controleurs ne dependent que de cette interface.
 */
interface Mailer
{
    /** @return bool false si l'envoi a echoue (l'erreur est journalisee par l'implementation) */
    public function send(string $destinataire, string $sujet, string $corps): bool;
}
