<?php

declare(strict_types=1);

/*
 * Consommateur RabbitMQ : applique le golden record client publie par
 * kbeauty-customer-mdm. Processus long, a laisser tourner a cote du serveur
 * web (en production : supervisor ou systemd, qui le relance s'il s'arrete).
 *
 *   php bin/consommer-clients-kbeauty.php
 */

use App\Core\Conteneur;
use App\Core\Database;
use App\Core\Env;
use App\Integration\Kbeauty\ConsommateurClientMiseAJour;
use App\Integration\Kbeauty\ContratClientMiseAJour;
use App\Service\MettreAJourClientGolden;
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

$consommateur = new ConsommateurClientMiseAJour(
    $connexion,
    new ContratClientMiseAJour(),
    static function (): MettreAJourClientGolden {
        Database::fermer();
        $conteneur = new Conteneur();
        (require __DIR__ . '/../config/services.php')($conteneur);

        return $conteneur->get(MettreAJourClientGolden::class);
    },
    static function (string $ligne): void {
        fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] {$ligne}\n");
    },
);

$consommateur->ecouter();
