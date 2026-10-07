<?php

declare(strict_types=1);

namespace App\Core\Mail;

use App\Core\Env;
use RuntimeException;

final class MailerFactory
{
    /**
     * MAIL_DRIVER=log (defaut) ou resend. Une configuration resend
     * incomplete leve une exception plutot que de retomber en silence sur
     * le log : sinon, en production, aucun email ne partirait sans que
     * personne ne s'en apercoive.
     */
    public static function depuisEnv(): Mailer
    {
        $driver = Env::get('MAIL_DRIVER', 'log');

        return match ($driver) {
            'log' => new LogMailer(),
            'resend' => new ResendMailer(
                self::obligatoire('RESEND_API_KEY'),
                self::obligatoire('MAIL_FROM'),
            ),
            default => throw new RuntimeException("MAIL_DRIVER inconnu : \"{$driver}\" (attendu : log ou resend)."),
        };
    }

    private static function obligatoire(string $cle): string
    {
        $valeur = Env::get($cle);

        if ($valeur === null || $valeur === '') {
            throw new RuntimeException("{$cle} doit etre renseigne dans .env quand MAIL_DRIVER=resend.");
        }

        return $valeur;
    }
}
