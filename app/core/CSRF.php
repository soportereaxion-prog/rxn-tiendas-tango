<?php

declare(strict_types=1);

namespace App\Core;

class CSRF
{
    private const SESSION_KEY = 'csrf_token';

    /**
     * Genera y retorna el token CSRF para la sesión actual.
     * @return string
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Valida cruzando el token almacenado con el extraído del request.
     * @param string|null $token
     * @return bool
     */
    public static function validate(?string $token): bool
    {
        if (empty($_SESSION[self::SESSION_KEY]) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    /**
     * Genera el input hidden listo para inyectar en forms.
     * @return string
     */
    public static function csrfField(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
