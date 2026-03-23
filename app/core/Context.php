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
        if (isset($_GET['empresa_id']) && is_numeric($_GET['empresa_id'])) {
            self::$empresaId = (int) $_GET['empresa_id'];
        } else {
            // Valor hardcodeado temporalmente.
            // Permite que otras entidades se prueben sin romper.
            self::$empresaId = 1;
        }
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
