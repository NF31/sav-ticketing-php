<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use Throwable;

/**
 * Taches executees apres l'envoi de la reponse au navigateur, typiquement
 * un appel reseau lent (envoi d'email via l'API Resend, ~0,4 s).
 *
 * Utile pour la vitesse, mais surtout pour la securite : sur "mot de
 * passe oublie", l'email n'est envoye que si le compte existe. Envoye
 * pendant la requete, il rendrait la reponse plus lente pour un compte
 * existant, et ce temps de reponse revelerait quels emails ont un compte.
 * Ici, la reponse part avant, dans le meme temps dans les deux cas.
 *
 * Sans file de messages (Redis, RabbitMQ...) : le meme processus PHP
 * termine la requete HTTP puis execute les taches. Suffisant a cette
 * echelle ; une file deviendrait utile pour reessayer un envoi echoue.
 */
final class ApresReponse
{
    /** @var list<Closure(): mixed> */
    private static array $taches = [];

    public static function ajouter(Closure $tache): void
    {
        self::$taches[] = $tache;
    }

    /**
     * A appeler en fin de public/index.php, qui a ouvert un tampon de sortie
     * (ob_start) en debut de requete : sans taches, la sortie est envoyee
     * normalement ; avec, la reponse est envoyee et la connexion fermee
     * avant de les executer.
     */
    public static function terminer(): void
    {
        if (self::$taches === []) {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            return;
        }

        // Le navigateur considere la reponse complete des qu'il a recu
        // Content-Length octets, et "Connection: close" lui evite d'attendre
        // la suite sur la meme connexion.
        $contenu = '';
        while (ob_get_level() > 0) {
            $contenu = ob_get_clean() . $contenu;
        }

        // Session enregistree et verrou libere avant d'envoyer la reponse :
        // sinon la requete suivante du navigateur (la redirection) attendrait
        // la fin des taches, et pourrait lire la session sans le message flash.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        ignore_user_abort(true);
        if (!headers_sent()) {
            header('Content-Length: ' . strlen($contenu));
            header('Connection: close');
        }
        echo $contenu;
        flush();

        // PHP-FPM (production) : termine la requete cote serveur web.
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        self::executerTaches();
    }

    /**
     * Chaque tache est isolee : une exception est journalisee (la reponse est
     * deja partie, il est trop tard pour une page d'erreur) et n'empeche pas
     * les taches suivantes.
     */
    public static function executerTaches(): void
    {
        $taches = self::$taches;
        self::$taches = [];

        foreach ($taches as $tache) {
            try {
                $tache();
            } catch (Throwable $e) {
                error_log('[ApresReponse] tache en echec : ' . $e::class . ' : ' . $e->getMessage());
            }
        }
    }
}
