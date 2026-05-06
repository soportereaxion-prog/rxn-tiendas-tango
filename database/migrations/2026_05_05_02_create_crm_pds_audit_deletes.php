<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Audit log de eliminaciones permanentes en crm_pedidos_servicio.
 *
 * Por qué existe (incidente del PDS X0065400007931, 2026-05-05): un PDS borrado
 * desde la papelera vía forceDelete pierde TODO rastro en RXN — no hay deleted_at,
 * no hay sync log, no hay before-state. Si ya estaba en Tango con número asignado,
 * queda huérfano allá afuera sin contraparte.
 *
 * El trigger BEFORE DELETE captura cualquier eliminación (PHP, phpMyAdmin, SQL
 * manual, otro tool). El repository setea @audit_user_id / @audit_user_name antes
 * del DELETE para atribución; si vienen NULL, el registro queda con NULL y eso
 * señaliza "delete no atribuible" sin perder el snapshot.
 */
return function (): void {
    $db = Database::getConnection();

    // 1) Tabla de audit
    $db->exec("
        CREATE TABLE IF NOT EXISTS crm_pedidos_servicio_audit_deletes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NOT NULL,
            empresa_id INT NOT NULL,
            numero VARCHAR(50) NULL,
            cliente_id INT NULL,
            cliente_nombre VARCHAR(255) NULL,
            fecha_inicio DATETIME NULL,
            fecha_finalizado DATETIME NULL,
            tango_nro_pedido VARCHAR(50) NULL,
            tango_estado INT NULL,
            usuario_id INT NULL,
            usuario_nombre VARCHAR(255) NULL,
            diagnostico TEXT NULL,
            solicito VARCHAR(255) NULL,
            before_json LONGTEXT NULL,
            deleted_by INT NULL,
            deleted_by_nombre VARCHAR(255) NULL,
            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_empresa (empresa_id),
            INDEX idx_audit_deleted_at (deleted_at),
            INDEX idx_audit_tango_nro (tango_nro_pedido),
            INDEX idx_audit_pedido_id (pedido_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 2) Trigger BEFORE DELETE — captura TODO delete sobre la tabla principal.
    // Drop primero por si la migración corre dos veces (idempotente).
    $db->exec("DROP TRIGGER IF EXISTS tr_crm_pds_audit_before_delete");

    $db->exec("
        CREATE TRIGGER tr_crm_pds_audit_before_delete
        BEFORE DELETE ON crm_pedidos_servicio
        FOR EACH ROW
        INSERT INTO crm_pedidos_servicio_audit_deletes (
            pedido_id, empresa_id, numero, cliente_id, cliente_nombre,
            fecha_inicio, fecha_finalizado, tango_nro_pedido, tango_estado,
            usuario_id, usuario_nombre, diagnostico, solicito,
            before_json, deleted_by, deleted_by_nombre
        ) VALUES (
            OLD.id, OLD.empresa_id, OLD.numero, OLD.cliente_id, OLD.cliente_nombre,
            OLD.fecha_inicio, OLD.fecha_finalizado, OLD.tango_nro_pedido, OLD.tango_estado,
            OLD.usuario_id, OLD.usuario_nombre, OLD.diagnostico, OLD.solicito,
            JSON_OBJECT(
                'id', OLD.id,
                'empresa_id', OLD.empresa_id,
                'numero', OLD.numero,
                'cliente_id', OLD.cliente_id,
                'cliente_nombre', OLD.cliente_nombre,
                'fecha_inicio', OLD.fecha_inicio,
                'fecha_finalizado', OLD.fecha_finalizado,
                'tango_nro_pedido', OLD.tango_nro_pedido,
                'tango_estado', OLD.tango_estado,
                'usuario_id', OLD.usuario_id,
                'usuario_nombre', OLD.usuario_nombre,
                'diagnostico', OLD.diagnostico,
                'solicito', OLD.solicito,
                'created_at', OLD.created_at,
                'updated_at', OLD.updated_at,
                'deleted_at', OLD.deleted_at
            ),
            @audit_user_id,
            @audit_user_name
        )
    ");

    // 3) Vista para RxnLive — etiqueta amigable del estado Tango y campos legibles.
    $db->exec("DROP VIEW IF EXISTS RXN_LIVE_VW_PDS_DELETES");

    $db->exec("
        CREATE VIEW RXN_LIVE_VW_PDS_DELETES AS
        SELECT
            a.id,
            a.empresa_id,
            a.pedido_id,
            a.numero,
            a.cliente_nombre AS cliente,
            a.fecha_inicio,
            a.fecha_finalizado,
            a.tango_nro_pedido,
            CASE a.tango_estado
                WHEN 2 THEN 'Aprobado'
                WHEN 3 THEN 'Cumplido'
                WHEN 4 THEN 'Cerrado'
                WHEN 5 THEN 'Anulado'
                WHEN NULL THEN 'Sin sync'
                ELSE 'Sin sync'
            END AS tango_estado_label,
            CASE WHEN a.tango_nro_pedido IS NOT NULL AND a.tango_nro_pedido <> ''
                 THEN 'Sí — quedó huérfano en Tango'
                 ELSE 'No'
            END AS estaba_en_tango,
            a.usuario_id,
            a.usuario_nombre AS tecnico,
            a.solicito AS solicitante,
            a.diagnostico,
            a.deleted_by,
            a.deleted_by_nombre AS eliminado_por,
            a.deleted_at,
            1 AS cantidad
        FROM crm_pedidos_servicio_audit_deletes a
    ");
};
