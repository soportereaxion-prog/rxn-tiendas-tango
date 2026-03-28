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

            $maxAttempts = 3;
            $lastException = null;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    self::$connection = new PDO($dsn, $config['user'], $config['pass'], [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]);
                    break;
                } catch (PDOException $e) {
                    $lastException = $e;
                    error_log(sprintf(
                        'Database connection error [attempt %d/%d] %s:%s - %s',
                        $attempt,
                        $maxAttempts,
                        (string) ($config['host'] ?? 'n/a'),
                        (string) ($config['port'] ?? 'n/a'),
                        $e->getMessage()
                    ));

                    if ($attempt < $maxAttempts) {
                        usleep(250000);
                    }
                }
            }

            if (self::$connection === null) {
                throw new \RuntimeException(sprintf(
                    'Error al conectar con la base de datos (%s:%s). Verificá que el motor MySQL esté levantado.',
                    (string) ($config['host'] ?? 'n/a'),
                    (string) ($config['port'] ?? 'n/a')
                ), previous: $lastException);
            }
        }

        return self::$connection;
    }

    // Evitar instanciación directa y clonación.
    private function __construct() {}
    private function __clone() {}
}
