<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Mail\Mailer;

/** Mailer de test : garde les emails en memoire au lieu de les envoyer. */
final class MailerEspion implements Mailer
{
    /** @var list<array{destinataire: string, sujet: string, corps: string}> */
    public array $envoyes = [];

    public function send(string $destinataire, string $sujet, string $corps): bool
    {
        $this->envoyes[] = ['destinataire' => $destinataire, 'sujet' => $sujet, 'corps' => $corps];

        return true;
    }
}
