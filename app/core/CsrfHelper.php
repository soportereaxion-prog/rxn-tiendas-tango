<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Utilidad simple anti-CSRF
 * (Cross-Site Request Forgery)
 */
class CsrfHelper
{
    /**
     * Genera un token si no existe en la sesión y lo retorna.
     */
    public static function generateToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (\Exception $e) {
                // Fallback seguro en extremo si random_bytes falla
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            }
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valida un token provisto contra el guardado en la sesión.
     * Utiliza hash_equals para resistir ataques de tiempo (timing attacks).
     */
    public static function validate(?string $token): bool
    {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Retorna el string de un campo input <hidden> listo para incrustar en formularios.
     */
    public static function input(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
