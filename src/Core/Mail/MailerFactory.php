<?php

declare(strict_types=1);

namespace App\Core\Mail;

use App\Core\Env;
use RuntimeException;

final class MailerFactory
{
    /**
     * MAIL_MAILER=log (defaut) ou resend, memes noms de variables que
     * Laravel (MAIL_MAILER, MAIL_FROM_ADDRESS, MAIL_FROM_NAME). Une
     * configuration resend incomplete leve une exception plutot que de
     * retomber en silence sur le log : sinon, en production, aucun email ne
     * partirait sans que personne ne s'en apercoive.
     */
    public static function depuisEnv(): Mailer
    {
        $mailer = Env::get('MAIL_MAILER', 'log');

        return match ($mailer) {
            'log' => new LogMailer(),
            'resend' => new ResendMailer(self::obligatoire('RESEND_API_KEY'), self::expediteur()),
            default => throw new RuntimeException("MAIL_MAILER inconnu : \"{$mailer}\" (attendu : log ou resend)."),
        };
    }

    /** "Nom <adresse>" si MAIL_FROM_NAME est renseigne, l'adresse seule sinon. */
    private static function expediteur(): string
    {
        $adresse = self::obligatoire('MAIL_FROM_ADDRESS');

        if (filter_var($adresse, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException("MAIL_FROM_ADDRESS n'est pas une adresse email valide : \"{$adresse}\".");
        }

        // Les caracteres qui structurent "Nom <adresse>" sont retires du nom.
        $nom = trim(str_replace(['<', '>', '"'], '', (string) Env::get('MAIL_FROM_NAME', '')));

        return $nom !== '' ? "{$nom} <{$adresse}>" : $adresse;
    }

    private static function obligatoire(string $cle): string
    {
        $valeur = Env::get($cle);

        if ($valeur === null || $valeur === '') {
            throw new RuntimeException("{$cle} doit etre renseigne dans .env quand MAIL_MAILER=resend.");
        }

        return $valeur;
    }
}
