<?php

declare(strict_types=1);

namespace App\Integration\Kbeauty;

use App\Service\ImportRetourKbeauty;
use Closure;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

/**
 * Ecoute les demandes de retour publiees par kbeauty-shop et en fait des
 * tickets. Processus long, lance par bin/consommer-retours-kbeauty.php.
 *
 * Le SAV possede ses files (il les declare) ; l'exchange kbeauty.exchange
 * appartient a l'infrastructure commune et n'est que verifie (declaration
 * passive) : le compte RabbitMQ du SAV n'a pas le droit de le modifier.
 *
 * Sort de chaque message :
 * - importe, ou deja importe -> ack (retire de la file) ;
 * - hors contrat -> reject sans remise en file : il part dans
 *   sav.retours.rejetes pour examen, il ne serait jamais valide ;
 * - erreur (base arretee...) -> remis en file et retente ; apres
 *   LIMITE_LIVRAISONS essais, RabbitMQ le range dans sav.retours.rejetes
 *   (file "quorum" : elle compte elle-meme les livraisons).
 */
final class ConsommateurRetours
{
    public const string EXCHANGE = 'kbeauty.exchange';
    public const string ROUTING_KEY = 'return_request.submitted';
    public const string FILE = 'sav.retours';
    public const string FILE_REJETS = 'sav.retours.rejetes';
    private const int LIMITE_LIVRAISONS = 5;
    private const int PAUSE_APRES_ERREUR_S = 10;

    /** @param Closure(): ImportRetourKbeauty $importFrais import avec une connexion a la base neuve */
    public function __construct(
        private readonly AMQPStreamConnection $connexion,
        private readonly ContratRetourSoumis $contrat,
        private readonly Closure $importFrais,
        private readonly Closure $journal,
    ) {
    }

    public function ecouter(): void
    {
        $canal = $this->connexion->channel();
        $this->declarerFiles($canal);

        // Un message a la fois : le suivant n'est envoye qu'apres l'ack du
        // precedent, rien ne se perd si le processus s'arrete.
        $canal->basic_qos(0, 1, false);
        $canal->basic_consume(self::FILE, no_ack: false, callback: $this->traiter(...));

        ($this->journal)('En ecoute sur la file ' . self::FILE . ' (Ctrl+C pour arreter).');

        while ($canal->is_consuming()) {
            $canal->wait();
        }
    }

    private function declarerFiles(AMQPChannel $canal): void
    {
        $canal->exchange_declare(self::EXCHANGE, 'direct', passive: true);

        $canal->exchange_declare(self::FILE_REJETS, 'fanout', durable: true, auto_delete: false);
        $canal->queue_declare(self::FILE_REJETS, durable: true, auto_delete: false);
        $canal->queue_bind(self::FILE_REJETS, self::FILE_REJETS);

        $canal->queue_declare(self::FILE, durable: true, auto_delete: false, arguments: new AMQPTable([
            'x-queue-type' => 'quorum',
            'x-delivery-limit' => self::LIMITE_LIVRAISONS,
            'x-dead-letter-exchange' => self::FILE_REJETS,
        ]));
        $canal->queue_bind(self::FILE, self::EXCHANGE, self::ROUTING_KEY);
    }

    private function traiter(AMQPMessage $message): void
    {
        try {
            $retour = $this->contrat->valider($message->getBody());
        } catch (MessageHorsContrat $e) {
            // Pas le corps du message dans le journal : il contient des
            // donnees personnelles (nom, email du client).
            ($this->journal)('Rejete vers ' . self::FILE_REJETS . " : {$e->getMessage()}");
            $message->reject(requeue: false);

            return;
        }

        try {
            $ticketId = ($this->importFrais)()->importer($retour);
        } catch (Throwable $e) {
            ($this->journal)("Retour {$retour->returnRequestId} : erreur, nouvel essai dans " . self::PAUSE_APRES_ERREUR_S . " s (" . $e::class . " : {$e->getMessage()})");
            $message->nack(requeue: true);
            sleep(self::PAUSE_APRES_ERREUR_S);

            return;
        }

        $message->ack();
        ($this->journal)($ticketId !== null
            ? "Retour {$retour->returnRequestId} : ticket {$ticketId} cree."
            : "Retour {$retour->returnRequestId} : deja importe, ignore.");
    }
}
