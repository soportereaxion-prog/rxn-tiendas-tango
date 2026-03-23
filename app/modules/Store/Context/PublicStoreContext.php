<?php
declare(strict_types=1);

namespace App\Modules\Store\Context;

/**
 * PublicStoreContext
 * 
 * Contiene la configuración global de la tienda pública, 
 * resuelta UNA SOLA VEZ por request basado en el SLUG (o futuro subdominio).
 */
class PublicStoreContext
{
    private static ?int $empresaId = null;
    private static ?array $empresaData = null;
    private static ?array $configStore = null;

    /**
     * Inicializa el contexto de la tienda pública con los datos recuperados de la DB.
     */
    public static function init(int $empresaId, array $empresaData, array $configStore): void
    {
        self::$empresaId = $empresaId;
        self::$empresaData = $empresaData;
        self::$configStore = $configStore;
    }

    public static function getEmpresaId(): ?int
    {
        return self::$empresaId;
    }

    public static function getEmpresaSlug(): ?string
    {
        return self::$empresaData['slug'] ?? null;
    }

    public static function getEmpresaNombre(): ?string
    {
        return self::$empresaData['nombre'] ?? null;
    }

    /**
     * Devuelve banderas de configuración.
     * En esta iteración devolvemos configs mockeados seguros,
     * y las extraídas del módulo de configuración si aplican.
     */
    public static function getFlags(): array
    {
        return [
            'mostrar_stock' => true,      // default configurable luego
            'permitir_sin_stock' => false // estricto default
        ];
    }
    
    public static function isInitialized(): bool 
    {
        return self::$empresaId !== null;
    }
}
