<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Permisos modulares por usuario — Fase 2 de la matriz de permisos.
 *
 * 1) Suma 11 columnas TINYINT a `usuarios`, una por cada módulo contratable
 *    a nivel empresa. DEFAULT 1: todos los usuarios existentes y nuevos
 *    arrancan con todos los módulos habilitados. Si la empresa NO tiene el
 *    módulo contratado, el flag a nivel usuario es indiferente — el corte
 *    duro lo hace `EmpresaAccessService`.
 *
 * 2) Crea `usuarios_modulos_audit` para registrar cambios de cualquiera de
 *    los 11 flags. Una fila por UPDATE (no una por flag) para no inflar el
 *    log: capturamos before/after en JSON.
 *
 * 3) Crea trigger AFTER UPDATE sobre `usuarios` que detecta cambios en los
 *    flags y emite el INSERT al audit. El `@audit_user_id` se setea desde
 *    PHP antes del UPDATE (mismo patrón que crm_pedidos_servicio_audit_deletes,
 *    release 1.46.3). Si viene NULL, el registro queda con NULL — eso
 *    señaliza "cambio no atribuible" sin perder el snapshot.
 */
return function (): void {
    $db = Database::getConnection();

    // 1) Columnas de permisos por usuario — DEFAULT 1 (todos habilitados).
    $columnas = [
        'usuario_modulo_notas'              => 'es_rxn_admin',
        'usuario_modulo_llamadas'           => 'usuario_modulo_notas',
        'usuario_modulo_monitoreo'          => 'usuario_modulo_llamadas',
        'usuario_modulo_rxn_live'           => 'usuario_modulo_monitoreo',
        'usuario_modulo_pedidos_servicio'   => 'usuario_modulo_rxn_live',
        'usuario_modulo_agenda'             => 'usuario_modulo_pedidos_servicio',
        'usuario_modulo_mail_masivos'       => 'usuario_modulo_agenda',
        'usuario_modulo_horas_turnero'      => 'usuario_modulo_mail_masivos',
        'usuario_modulo_geo_tracking'       => 'usuario_modulo_horas_turnero',
        'usuario_modulo_presupuestos_pwa'   => 'usuario_modulo_geo_tracking',
        'usuario_modulo_horas_pwa'          => 'usuario_modulo_presupuestos_pwa',
    ];

    foreach ($columnas as $columna => $afterCol) {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'usuarios'
              AND column_name = :col
        ");
        $stmt->execute([':col' => $columna]);
        if ((int) $stmt->fetchColumn() === 0) {
            $db->exec(sprintf(
                "ALTER TABLE usuarios ADD COLUMN %s TINYINT(1) NOT NULL DEFAULT 1 AFTER %s",
                $columna,
                $afterCol
            ));
        }
    }

    // 2) Tabla de audit
    $db->exec("
        CREATE TABLE IF NOT EXISTS usuarios_modulos_audit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            empresa_id INT NOT NULL,
            flags_before JSON NULL,
            flags_after JSON NULL,
            changed_by INT NULL,
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_usuario (usuario_id, changed_at),
            INDEX idx_audit_empresa (empresa_id, changed_at),
            INDEX idx_audit_changed_by (changed_by, changed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 3) Trigger AFTER UPDATE — solo loggea si cambió al menos un flag.
    $db->exec("DROP TRIGGER IF EXISTS tr_usuarios_modulos_audit_after_update");

    $db->exec("
        CREATE TRIGGER tr_usuarios_modulos_audit_after_update
        AFTER UPDATE ON usuarios
        FOR EACH ROW
        INSERT INTO usuarios_modulos_audit (usuario_id, empresa_id, flags_before, flags_after, changed_by)
        SELECT NEW.id, NEW.empresa_id,
            JSON_OBJECT(
                'usuario_modulo_notas',            OLD.usuario_modulo_notas,
                'usuario_modulo_llamadas',         OLD.usuario_modulo_llamadas,
                'usuario_modulo_monitoreo',        OLD.usuario_modulo_monitoreo,
                'usuario_modulo_rxn_live',         OLD.usuario_modulo_rxn_live,
                'usuario_modulo_pedidos_servicio', OLD.usuario_modulo_pedidos_servicio,
                'usuario_modulo_agenda',           OLD.usuario_modulo_agenda,
                'usuario_modulo_mail_masivos',     OLD.usuario_modulo_mail_masivos,
                'usuario_modulo_horas_turnero',    OLD.usuario_modulo_horas_turnero,
                'usuario_modulo_geo_tracking',     OLD.usuario_modulo_geo_tracking,
                'usuario_modulo_presupuestos_pwa', OLD.usuario_modulo_presupuestos_pwa,
                'usuario_modulo_horas_pwa',        OLD.usuario_modulo_horas_pwa
            ),
            JSON_OBJECT(
                'usuario_modulo_notas',            NEW.usuario_modulo_notas,
                'usuario_modulo_llamadas',         NEW.usuario_modulo_llamadas,
                'usuario_modulo_monitoreo',        NEW.usuario_modulo_monitoreo,
                'usuario_modulo_rxn_live',         NEW.usuario_modulo_rxn_live,
                'usuario_modulo_pedidos_servicio', NEW.usuario_modulo_pedidos_servicio,
                'usuario_modulo_agenda',           NEW.usuario_modulo_agenda,
                'usuario_modulo_mail_masivos',     NEW.usuario_modulo_mail_masivos,
                'usuario_modulo_horas_turnero',    NEW.usuario_modulo_horas_turnero,
                'usuario_modulo_geo_tracking',     NEW.usuario_modulo_geo_tracking,
                'usuario_modulo_presupuestos_pwa', NEW.usuario_modulo_presupuestos_pwa,
                'usuario_modulo_horas_pwa',        NEW.usuario_modulo_horas_pwa
            ),
            @audit_user_id
        WHERE
            OLD.usuario_modulo_notas <> NEW.usuario_modulo_notas
            OR OLD.usuario_modulo_llamadas <> NEW.usuario_modulo_llamadas
            OR OLD.usuario_modulo_monitoreo <> NEW.usuario_modulo_monitoreo
            OR OLD.usuario_modulo_rxn_live <> NEW.usuario_modulo_rxn_live
            OR OLD.usuario_modulo_pedidos_servicio <> NEW.usuario_modulo_pedidos_servicio
            OR OLD.usuario_modulo_agenda <> NEW.usuario_modulo_agenda
            OR OLD.usuario_modulo_mail_masivos <> NEW.usuario_modulo_mail_masivos
            OR OLD.usuario_modulo_horas_turnero <> NEW.usuario_modulo_horas_turnero
            OR OLD.usuario_modulo_geo_tracking <> NEW.usuario_modulo_geo_tracking
            OR OLD.usuario_modulo_presupuestos_pwa <> NEW.usuario_modulo_presupuestos_pwa
            OR OLD.usuario_modulo_horas_pwa <> NEW.usuario_modulo_horas_pwa
    ");
};
