<?php

declare(strict_types=1);

namespace App\Modules\Auth;

/**
 * Chequea permisos de módulos a nivel USUARIO (segunda capa de la matriz).
 *
 * Cascada: el corte duro ya lo hace `EmpresaAccessService::hasCrmXxxAccess()`
 * — si la empresa NO contrata el módulo, este servicio ni se llama.
 *
 * Reglas de bypass:
 *  - `es_rxn_admin = 1` (super admin Reaxion) → acceso a todo siempre.
 *  - `es_admin = 1` (admin de empresa) → acceso a todos los módulos
 *    contratados a nivel empresa, sin necesidad de tildarse a sí mismo.
 *  - Usuario normal → tiene que tener el flag `usuario_modulo_<key> = 1`.
 *
 * Las claves válidas (mismas de `usuarios.usuario_modulo_*`):
 *  notas, llamadas, monitoreo, rxn_live, pedidos_servicio, agenda,
 *  mail_masivos, horas_turnero, geo_tracking, presupuestos_pwa, horas_pwa.
 */
class UserModuleAccessService
{
    private static bool $loaded = false;
    private static ?Usuario $usuario = null;

    public static function current(): ?Usuario
    {
        if (self::$loaded) {
            return self::$usuario;
        }

        self::$loaded = true;
        $userId = $_SESSION['user_id'] ?? null;

        if (!is_numeric($userId)) {
            self::$usuario = null;
            return null;
        }

        $repo = new UsuarioRepository();
        self::$usuario = $repo->findById((int) $userId);

        return self::$usuario;
    }

    public static function userHas(string $moduleKey): bool
    {
        $usuario = self::current();

        if (!$usuario) {
            return false;
        }

        if ((int) ($usuario->es_rxn_admin ?? 0) === 1) {
            return true;
        }

        if ((int) ($usuario->es_admin ?? 0) === 1) {
            return true;
        }

        $col = 'usuario_modulo_' . $moduleKey;
        return property_exists($usuario, $col) && (int) ($usuario->$col ?? 0) === 1;
    }

    public static function requireUserAccess(string $moduleKey, string $environmentLabel): void
    {
        AuthService::requireLogin();

        if (!self::userHas($moduleKey)) {
            self::deny($environmentLabel);
        }
    }

    /**
     * Helper para vistas: devuelve el array de flags por módulo del usuario actual.
     * Útil para el launcher PWA y otras vistas que decidan visibilidad.
     */
    public static function flags(): array
    {
        $usuario = self::current();
        if (!$usuario) {
            return [];
        }

        return [
            'notas'             => (int) ($usuario->usuario_modulo_notas ?? 0),
            'llamadas'          => (int) ($usuario->usuario_modulo_llamadas ?? 0),
            'monitoreo'         => (int) ($usuario->usuario_modulo_monitoreo ?? 0),
            'rxn_live'          => (int) ($usuario->usuario_modulo_rxn_live ?? 0),
            'pedidos_servicio'  => (int) ($usuario->usuario_modulo_pedidos_servicio ?? 0),
            'agenda'            => (int) ($usuario->usuario_modulo_agenda ?? 0),
            'mail_masivos'      => (int) ($usuario->usuario_modulo_mail_masivos ?? 0),
            'horas_turnero'     => (int) ($usuario->usuario_modulo_horas_turnero ?? 0),
            'geo_tracking'      => (int) ($usuario->usuario_modulo_geo_tracking ?? 0),
            'presupuestos_pwa'  => (int) ($usuario->usuario_modulo_presupuestos_pwa ?? 0),
            'horas_pwa'         => (int) ($usuario->usuario_modulo_horas_pwa ?? 0),
        ];
    }

    private static function deny(string $environmentLabel): void
    {
        http_response_code(403);
        echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
        echo '<h2>Acceso denegado</h2>';
        echo '<p>Tu usuario no tiene habilitado ' . htmlspecialchars($environmentLabel, ENT_QUOTES, 'UTF-8') . '. ';
        echo 'Pedile al administrador de tu empresa que te lo habilite.</p>';
        echo "<a href='/'>Volver al launcher</a>";
        echo '</div>';
        exit;
    }
}
