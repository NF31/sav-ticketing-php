<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Point d'acces unique a la connexion PDO. Toutes les requetes de
 * l'application passent par des requetes preparees via cette instance :
 * c'est le point precis qui manquait dans le projet original
 * (SELECT * sans WHERE, filtrage fait en PHP apres coup).
 */
final class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', '3306');
        $name = Env::get('DB_NAME', 'sav_ticketing');
        $user = Env::get('DB_USER', 'root');
        $password = Env::get('DB_PASSWORD', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            self::$instance = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // rowCount() d'un UPDATE = lignes trouvees par le WHERE, et non
                // lignes reellement changees : reassigner le meme technicien
                // compte alors comme un succes, pas comme un echec.
                Pdo\Mysql::ATTR_FOUND_ROWS => true,
            ]);
        } catch (PDOException $e) {
            throw new PDOException('Connexion a la base impossible : ' . $e->getMessage(), (int) $e->getCode());
        }

        return self::$instance;
    }

    /**
     * Oublie la connexion : la suivante est ouverte a neuf. Pour les processus
     * longs (bin/consommer-retours-kbeauty.php), qui ne peuvent pas compter
     * sur une connexion ouverte depuis des heures.
     */
    public static function fermer(): void
    {
        self::$instance = null;
    }
}
