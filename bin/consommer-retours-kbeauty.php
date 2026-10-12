<?php

declare(strict_types=1);

/*
 * Consommateur RabbitMQ : transforme les demandes de retour de kbeauty-shop
 * en tickets SAV. Processus long, a laisser tourner a cote du serveur web
 * (en production : supervisor ou systemd, qui le relance s'il s'arrete).
 *
 *   php bin/consommer-retours-kbeauty.php
 */

use App\Core\Conteneur;
use App\Core\Database;
use App\Core\Env;
use App\Integration\Kbeauty\ConsommateurRetours;
use App\Integration\Kbeauty\ContratRetourSoumis;
use App\Service\ImportRetourKbeauty;
use PhpAmqpLib\Connection\AMQPStreamConnection;

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

Env::load(__DIR__ . '/../.env');

$connexion = new AMQPStreamConnection(
    (string) Env::get('RABBITMQ_HOST', '127.0.0.1'),
    (int) Env::get('RABBITMQ_PORT', '5672'),
    (string) Env::get('RABBITMQ_USER', 'sav'),
    (string) Env::get('RABBITMQ_PASSWORD', ''),
    (string) Env::get('RABBITMQ_VHOST', '/'),
);

$consommateur = new ConsommateurRetours(
    $connexion,
    new ContratRetourSoumis(),
    // Une connexion a la base par message, comme une requete HTTP : un
    // processus qui tourne des jours garderait sinon une connexion coupee
    // par MariaDB (wait_timeout) ou par un redemarrage de la base.
    static function (): ImportRetourKbeauty {
        Database::fermer();
        $conteneur = new Conteneur();
        (require __DIR__ . '/../config/services.php')($conteneur);

        return $conteneur->get(ImportRetourKbeauty::class);
    },
    static function (string $ligne): void {
        fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] {$ligne}\n");
    },
);

$consommateur->ecouter();
