<?php

declare(strict_types=1);

namespace App\Modules\Empresas;

use App\Modules\Auth\AuthService;

class EmpresaAccessService
{
    private static bool $loaded = false;
    private static ?Empresa $empresa = null;

    public static function current(): ?Empresa
    {
        if (self::$loaded) {
            return self::$empresa;
        }

        self::$loaded = true;
        $empresaId = $_SESSION['empresa_id'] ?? null;

        if (!is_numeric($empresaId)) {
            self::$empresa = null;
            return null;
        }

        $repository = new EmpresaRepository();
        self::$empresa = $repository->findById((int) $empresaId);

        return self::$empresa;
    }

    public static function hasTiendasAccess(): bool
    {
        $empresa = self::current();

        return $empresa !== null
            && (int) $empresa->activa === 1
            && (int) $empresa->modulo_tiendas === 1;
    }

    public static function hasTiendasNotasAccess(): bool
    {
        $empresa = self::current();

        return self::hasTiendasAccess() && (int) $empresa->tiendas_modulo_notas === 1;
    }

    public static function hasCrmAccess(): bool
    {
        $empresa = self::current();

        return $empresa !== null
            && (int) $empresa->activa === 1
            && (int) $empresa->modulo_crm === 1;
    }

    public static function hasCrmNotasAccess(): bool
    {
        $empresa = self::current();

        return self::hasCrmAccess() && (int) $empresa->crm_modulo_notas === 1;
    }

    public static function hasAnyOperationalAccess(): bool
    {
        return self::hasTiendasAccess() || self::hasCrmAccess();
    }

    public static function requireTiendasAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasTiendasAccess()) {
            self::deny('Entorno Operativo de Tiendas');
        }
    }

    public static function requireCrmAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasCrmAccess()) {
            self::deny('Entorno Operativo de CRM');
        }
    }

    public static function requireTiendasNotasAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasTiendasNotasAccess()) {
            self::deny('Módulo de Notas en Tiendas');
        }
    }

    public static function requireCrmNotasAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasCrmNotasAccess()) {
            self::deny('Módulo de Notas en CRM');
        }
    }

    public static function requireAnyOperationalAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasAnyOperationalAccess()) {
            self::deny('Entornos Operativos');
        }
    }

    private static function deny(string $environmentLabel): void
    {
        http_response_code(403);
        echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
        echo '<h2>Acceso denegado</h2>';
        echo '<p>El tenant actual no tiene habilitado ' . htmlspecialchars($environmentLabel, ENT_QUOTES, 'UTF-8') . '.</p>';
        echo "<a href='/'>Volver al launcher</a>";
        echo '</div>';
        exit;
    }
}
