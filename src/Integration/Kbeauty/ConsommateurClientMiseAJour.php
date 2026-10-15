<?php

declare(strict_types=1);

namespace App\Integration\Kbeauty;

use App\Service\MettreAJourClientGolden;
use Closure;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

/**
 * Ecoute le golden record client publie par kbeauty-customer-mdm et met a
 * jour la copie locale du client. Meme structure que ConsommateurRetours
 * (doc 20 cote kbeauty-shop) : exchange partage verifie en declaration
 * passive, file quorum avec dead-letter, un message a la fois.
 *
 * Sort de chaque message :
 * - applique -> ack ;
 * - hors contrat -> reject sans remise en file -> sav.clients.rejetes ;
 * - conflit d'email avec un autre client, ou erreur (base arretee...) ->
 *   remis en file et retente ; apres LIMITE_LIVRAISONS essais, RabbitMQ le
 *   range dans sav.clients.rejetes pour examen manuel.
 */
final class ConsommateurClientMiseAJour
{
    public const string EXCHANGE = 'kbeauty.exchange';
    public const string ROUTING_KEY = 'customer.golden_record.updated';
    public const string FILE = 'sav.clients';
    public const string FILE_REJETS = 'sav.clients.rejetes';
    private const int LIMITE_LIVRAISONS = 5;
    private const int PAUSE_APRES_ERREUR_S = 10;

    /** @param Closure(): MettreAJourClientGolden $serviceFrais service avec une connexion a la base neuve */
    public function __construct(
        private readonly AMQPStreamConnection $connexion,
        private readonly ContratClientMiseAJour $contrat,
        private readonly Closure $serviceFrais,
        private readonly Closure $journal,
    ) {
    }

    public function ecouter(): void
    {
        $canal = $this->connexion->channel();
        $this->declarerFiles($canal);

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
            $goldenRecord = $this->contrat->valider($message->getBody());
        } catch (MessageHorsContrat $e) {
            // Pas le corps du message dans le journal : il contient des
            // donnees personnelles (nom, email du client).
            ($this->journal)('Rejete vers ' . self::FILE_REJETS . " : {$e->getMessage()}");
            $message->reject(requeue: false);

            return;
        }

        try {
            $applique = ($this->serviceFrais)()->appliquer($goldenRecord);
        } catch (Throwable $e) {
            ($this->journal)("Client {$goldenRecord->sourceUserId} : erreur, nouvel essai dans " . self::PAUSE_APRES_ERREUR_S . " s (" . $e::class . " : {$e->getMessage()})");
            $message->nack(requeue: true);
            sleep(self::PAUSE_APRES_ERREUR_S);

            return;
        }

        if (!$applique) {
            ($this->journal)("Client {$goldenRecord->sourceUserId} : conflit d'email avec un autre client, nouvel essai dans " . self::PAUSE_APRES_ERREUR_S . ' s');
            $message->nack(requeue: true);
            sleep(self::PAUSE_APRES_ERREUR_S);

            return;
        }

        $message->ack();
        ($this->journal)("Client {$goldenRecord->sourceUserId} : identite mise a jour.");
    }
}
