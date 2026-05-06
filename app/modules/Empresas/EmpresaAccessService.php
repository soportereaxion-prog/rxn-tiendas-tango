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

    public static function hasTiendasRxnLiveAccess(): bool
    {
        $empresa = self::current();
        return $empresa !== null && (int) $empresa->activa === 1 && (int) $empresa->tiendas_modulo_rxn_live === 1;
    }

    public static function hasCrmRxnLiveAccess(): bool
    {
        $empresa = self::current();
        return $empresa !== null && (int) $empresa->activa === 1 && (int) $empresa->crm_modulo_rxn_live === 1;
    }

    public static function hasCrmLlamadasAccess(): bool
    {
        $empresa = self::current();
        return self::hasCrmAccess() && (int) $empresa->crm_modulo_llamadas === 1;
    }

    public static function hasCrmMonitoreoAccess(): bool
    {
        $empresa = self::current();
        return self::hasCrmAccess() && (int) $empresa->crm_modulo_monitoreo === 1;
    }

    public static function hasCrmPedidosServicioAccess(): bool
    {
        $empresa = self::current();
        return self::hasCrmAccess() && (int) $empresa->crm_modulo_pedidos_servicio === 1;
    }

    public static function hasCrmAgendaAccess(): bool
    {
        $empresa = self::current();
        return self::hasCrmAccess() && (int) $empresa->crm_modulo_agenda === 1;
    }

    public static function hasCrmMailMasivosAccess(): bool
    {
        $empresa = self::current();
        return self::hasCrmAccess() && (int) $empresa->crm_modulo_mail_masivos === 1;
    }

    public static function hasCrmHorasTurneroAccess(): bool
    {
        $empresa = self::current();
        return self::hasCrmAccess() && (int) $empresa->crm_modulo_horas_turnero === 1;
    }

    public static function hasCrmGeoTrackingAccess(): bool
    {
        $empresa = self::current();
        return self::hasCrmAccess() && (int) $empresa->crm_modulo_geo_tracking === 1;
    }

    public static function hasCrmPresupuestosPwaAccess(): bool
    {
        $empresa = self::current();
        return self::hasCrmAccess() && (int) $empresa->crm_modulo_presupuestos_pwa === 1;
    }

    public static function hasCrmHorasPwaAccess(): bool
    {
        $empresa = self::current();
        return self::hasCrmAccess() && (int) $empresa->crm_modulo_horas_pwa === 1;
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

    public static function requireRxnLiveAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasTiendasRxnLiveAccess() && !self::hasCrmRxnLiveAccess()) {
            self::deny('Módulo RXN Live');
        }
    }

    public static function requireCrmLlamadasAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasCrmLlamadasAccess()) {
            self::deny('Módulo de Llamadas en CRM');
        }
    }

    public static function requireCrmMonitoreoAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasCrmMonitoreoAccess()) {
            self::deny('Módulo de Monitoreo de Usuarios en CRM');
        }
    }

    public static function requireCrmPedidosServicioAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasCrmPedidosServicioAccess()) {
            self::deny('Módulo de Pedidos de Servicio en CRM');
        }
    }

    public static function requireCrmAgendaAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasCrmAgendaAccess()) {
            self::deny('Módulo de Agenda en CRM');
        }
    }

    public static function requireCrmMailMasivosAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasCrmMailMasivosAccess()) {
            self::deny('Módulo de Mail Masivos en CRM');
        }
    }

    public static function requireCrmHorasTurneroAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasCrmHorasTurneroAccess()) {
            self::deny('Módulo de Horas (Turnero) en CRM');
        }
    }

    public static function requireCrmGeoTrackingAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasCrmGeoTrackingAccess()) {
            self::deny('Módulo de Geo Tracking en CRM');
        }
    }

    public static function requireCrmPresupuestosPwaAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasCrmPresupuestosPwaAccess()) {
            self::deny('Módulo de Presupuestos PWA');
        }
    }

    public static function requireCrmHorasPwaAccess(): void
    {
        AuthService::requireLogin();

        if (!self::hasCrmHorasPwaAccess()) {
            self::deny('Módulo de Horas PWA');
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
