<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Gestiona el contexto de la petición actual.
 * En el futuro contendrá información de sesión, usuario logueado, etc.
 */
class Context
{
    private static ?int $empresaId = null;

    /**
     * Inicializa el estado base.
     * Temporalmente (Fase 0) lee desde $_GET['empresa_id'] o por defecto 1,
     * para poder emular estar dentro de una empresa sin necesidad de Auth.
     */
    public static function init(): void
    {
        // 1. Prioridad Absoluta: Sesión autenticada.
        if (isset($_SESSION['empresa_id']) && is_numeric($_SESSION['empresa_id'])) {
            self::$empresaId = (int) $_SESSION['empresa_id'];
            return;
        }

        // 2. Fallback temporal de desarrollo (Deshabilitado por defecto y marcado como transitorio)
        $useDevFallback = false;
        if ($useDevFallback && isset($_GET['empresa_id']) && is_numeric($_GET['empresa_id'])) {
            self::$empresaId = (int) $_GET['empresa_id'];
            return;
        }

        // 3. Fallo controlado (nulo, lo cual detonará protección en los servicios/guards)
        self::$empresaId = null;
    }

    public static function getEmpresaId(): ?int
    {
        return self::$empresaId;
    }

    public static function setEmpresaId(?int $id): void
    {
        self::$empresaId = $id;
    }
}
