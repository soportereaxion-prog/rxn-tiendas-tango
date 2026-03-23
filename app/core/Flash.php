<?php
declare(strict_types=1);

namespace App\Core;

class Flash
{
    private const SESSION_KEY = 'flash_messages';

    /**
     * @param string $type success, danger, warning, info
     * @param string $message Mensaje a mostrar
     * @param array $stats Metricas opcionales (recibidos, insertados, etc)
     */
    public static function set(string $type, string $message, array $stats = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION[self::SESSION_KEY] = [
            'type' => $type,
            'message' => $message,
            'stats' => $stats
        ];
    }

    public static function has(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION[self::SESSION_KEY]);
    }

    public static function get(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION[self::SESSION_KEY])) {
            $flash = $_SESSION[self::SESSION_KEY];
            unset($_SESSION[self::SESSION_KEY]);
            return $flash;
        }

        return null;
    }
}
