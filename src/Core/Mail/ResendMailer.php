<?php

declare(strict_types=1);

namespace App\Core\Mail;

use Closure;

/**
 * Envoi reel via l'API HTTP de Resend (https://resend.com/docs/api-reference/emails/send-email),
 * appelee directement avec l'extension curl : c'est la meme requete que
 * celle du SDK officiel (resend/resend-php), sans ses dependances (Guzzle...).
 */
final class ResendMailer implements Mailer
{
    private const string URL_API = 'https://api.resend.com/emails';

    /** @var Closure(string $url, array<string, string> $entetes, string $json): array{statut: int, corps: string} */
    private Closure $requeteHttp;

    /**
     * @param string $expediteur ex. "SAV Ticketing <noreply@mondomaine.fr>" ; le domaine doit etre verifie chez Resend
     * @param Closure|null $requeteHttp injectable pour les tests (aucun appel reseau) ; curl par defaut
     */
    public function __construct(
        private readonly string $cleApi,
        private readonly string $expediteur,
        ?Closure $requeteHttp = null,
    ) {
        $this->requeteHttp = $requeteHttp ?? self::requeteCurl(...);
    }

    public function send(string $destinataire, string $sujet, string $corps): bool
    {
        $json = json_encode([
            'from' => $this->expediteur,
            'to' => [$destinataire],
            'subject' => $sujet,
            'text' => $corps,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $reponse = ($this->requeteHttp)(self::URL_API, [
            'Authorization' => 'Bearer ' . $this->cleApi,
            'Content-Type' => 'application/json',
        ], $json);

        if ($reponse['statut'] >= 200 && $reponse['statut'] < 300) {
            return true;
        }

        // Journalise le statut et le message de Resend (ex. 403 "domaine non
        // verifie"), jamais la cle API. Le destinataire n'est pas journalise
        // non plus : les logs ne doivent pas lister les emails des comptes.
        error_log(sprintf(
            '[ResendMailer] envoi echoue (HTTP %d) : %s',
            $reponse['statut'],
            mb_substr($reponse['corps'], 0, 300),
        ));

        return false;
    }

    /**
     * @param array<string, string> $entetes
     * @return array{statut: int, corps: string} statut 0 si la requete n'a pas abouti (reseau, timeout)
     */
    private static function requeteCurl(string $url, array $entetes, string $json): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => array_map(
                static fn (string $nom, string $valeur): string => "{$nom}: {$valeur}",
                array_keys($entetes),
                $entetes,
            ),
            CURLOPT_RETURNTRANSFER => true,
            // Limites courtes : l'envoi se fait apres la reponse au navigateur
            // (ApresReponse), mais un processus PHP bloque reste un processus
            // indisponible pour les autres requetes.
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);

        $corps = curl_exec($curl);
        $statut = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if ($corps === false) {
            return ['statut' => 0, 'corps' => 'erreur curl : ' . curl_error($curl)];
        }

        return ['statut' => $statut, 'corps' => (string) $corps];
    }
}
