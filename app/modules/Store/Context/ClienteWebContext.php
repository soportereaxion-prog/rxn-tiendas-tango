<?php

declare(strict_types=1);

namespace App\Modules\Store\Context;

/**
 * ClienteWebContext
 * Maneja la sesión exclusiva para los clientes compradores de la Tienda (B2C).
 * Totalmente aislada de $_SESSION['admin_id'] y protegiendo el scope de empresa.
 */
class ClienteWebContext
{
    /**
     * Inicia o resume la sesión de forma segura sin regenerar masivamente
     * si ya está iniciada por el App\Core\Session (que es ideal).
     */
    private static function checkSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(int $clienteWebId, int $empresaId, array $clienteData): void
    {
        self::checkSession();
        $_SESSION['store_cliente_id'] = $clienteWebId;
        $_SESSION['store_empresa_id'] = $empresaId;
        $_SESSION['store_cliente_nombre'] = $clienteData['nombre'];
        $_SESSION['store_cliente_email'] = $clienteData['email'];
        $_SESSION['store_cliente_apellido'] = $clienteData['apellido'] ?? '';
    }

    public static function logout(): void
    {
        self::checkSession();
        unset($_SESSION['store_cliente_id']);
        unset($_SESSION['store_empresa_id']);
        unset($_SESSION['store_cliente_nombre']);
        unset($_SESSION['store_cliente_email']);
        unset($_SESSION['store_cliente_apellido']);
    }

    public static function isLoggedIn(int $empresaIdActual): bool
    {
        self::checkSession();
        return isset($_SESSION['store_cliente_id']) 
            && isset($_SESSION['store_empresa_id']) 
            && (int)$_SESSION['store_empresa_id'] === $empresaIdActual;
    }

    public static function getClienteId(): ?int
    {
        self::checkSession();
        return $_SESSION['store_cliente_id'] ?? null;
    }

    public static function getClienteNombre(): ?string
    {
        self::checkSession();
        return $_SESSION['store_cliente_nombre'] ?? null;
    }

    public static function getClienteEmail(): ?string
    {
        self::checkSession();
        return $_SESSION['store_cliente_email'] ?? null;
    }
}
