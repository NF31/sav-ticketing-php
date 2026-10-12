<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Env;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * L'application se connecte avec un compte limite (database/utilisateur_app.sql),
 * pas avec root : verifie qu'il ne peut pas modifier le schema. Echoue si
 * .env pointe encore sur root.
 */
final class DroitsBaseTest extends TestCase
{
    public function testLeCompteDeLApplicationNePeutPasModifierLeSchema(): void
    {
        Env::load(__DIR__ . '/../../.env');
        $pdo = Database::connection();

        try {
            $pdo->exec('CREATE TABLE droits_test (id INT)');
        } catch (PDOException $e) {
            // 1142 : commande refusee a cet utilisateur.
            self::assertSame(1142, $e->errorInfo[1] ?? null, $e->getMessage());

            return;
        }

        $pdo->exec('DROP TABLE droits_test');
        self::fail('Le compte de DB_USER peut creer des tables : utiliser sav_app (database/utilisateur_app.sql), pas root.');
    }
}
