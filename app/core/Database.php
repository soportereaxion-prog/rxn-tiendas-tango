<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    /**
     * Devuelve la conexión PDO (singleton).
     * La configuración se toma de app/config/database.php.
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $config = require BASE_PATH . '/app/config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );

            try {
                self::$connection = new PDO($dsn, $config['user'], $config['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // No exponer detalles de conexión al cliente.
                error_log('Database connection error: ' . $e->getMessage());
                throw new \RuntimeException('Error al conectar con la base de datos.');
            }
        }

        return self::$connection;
    }

    // Evitar instanciación directa y clonación.
    private function __construct() {}
    private function __clone() {}
}
