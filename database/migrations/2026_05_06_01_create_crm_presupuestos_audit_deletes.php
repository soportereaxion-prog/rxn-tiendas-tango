<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Audit log de eliminaciones permanentes en crm_presupuestos.
 *
 * Réplica del patrón implementado en 1.46.3 para crm_pedidos_servicio.
 * Mismo motivo: si un presupuesto pusheado a Tango (con nro_comprobante_tango)
 * se borra desde la papelera, queda huérfano en Tango sin trazabilidad en RXN.
 *
 * Trigger BEFORE DELETE captura cualquier eliminación (PHP, phpMyAdmin, SQL
 * manual). El repository setea @audit_user_id / @audit_user_name antes del
 * DELETE para atribución; si vienen NULL queda señalizado como "no atribuible".
 */
return function (): void {
    $db = Database::getConnection();

    // 1) Tabla de audit
    $db->exec("
        CREATE TABLE IF NOT EXISTS crm_presupuestos_audit_deletes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            presupuesto_id INT NOT NULL,
            empresa_id INT NOT NULL,
            numero INT NULL,
            fecha DATETIME NULL,
            cliente_id INT NULL,
            cliente_nombre VARCHAR(255) NULL,
            total DECIMAL(15,2) NULL,
            estado VARCHAR(30) NULL,
            tango_sync_status VARCHAR(50) NULL,
            nro_comprobante_tango VARCHAR(100) NULL,
            tango_sync_date DATETIME NULL,
            usuario_id INT NULL,
            usuario_nombre VARCHAR(180) NULL,
            tratativa_id INT NULL,
            version_numero INT NULL,
            version_padre_id INT NULL,
            comentarios TEXT NULL,
            observaciones TEXT NULL,
            before_json LONGTEXT NULL,
            deleted_by INT NULL,
            deleted_by_nombre VARCHAR(255) NULL,
            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_pres_empresa (empresa_id),
            INDEX idx_audit_pres_deleted_at (deleted_at),
            INDEX idx_audit_pres_tango_nro (nro_comprobante_tango),
            INDEX idx_audit_pres_presupuesto_id (presupuesto_id),
            INDEX idx_audit_pres_tratativa (tratativa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 2) Trigger BEFORE DELETE — captura TODO delete sobre la tabla principal.
    $db->exec("DROP TRIGGER IF EXISTS tr_crm_presupuestos_audit_before_delete");

    $db->exec("
        CREATE TRIGGER tr_crm_presupuestos_audit_before_delete
        BEFORE DELETE ON crm_presupuestos
        FOR EACH ROW
        INSERT INTO crm_presupuestos_audit_deletes (
            presupuesto_id, empresa_id, numero, fecha,
            cliente_id, cliente_nombre, total, estado,
            tango_sync_status, nro_comprobante_tango, tango_sync_date,
            usuario_id, usuario_nombre,
            tratativa_id, version_numero, version_padre_id,
            comentarios, observaciones,
            before_json, deleted_by, deleted_by_nombre
        ) VALUES (
            OLD.id, OLD.empresa_id, OLD.numero, OLD.fecha,
            OLD.cliente_id, OLD.cliente_nombre_snapshot, OLD.total, OLD.estado,
            OLD.tango_sync_status, OLD.nro_comprobante_tango, OLD.tango_sync_date,
            OLD.usuario_id, OLD.usuario_nombre,
            OLD.tratativa_id, OLD.version_numero, OLD.version_padre_id,
            OLD.comentarios, OLD.observaciones,
            JSON_OBJECT(
                'id', OLD.id,
                'empresa_id', OLD.empresa_id,
                'tratativa_id', OLD.tratativa_id,
                'version_padre_id', OLD.version_padre_id,
                'version_numero', OLD.version_numero,
                'numero', OLD.numero,
                'fecha', OLD.fecha,
                'cliente_id', OLD.cliente_id,
                'cliente_nombre_snapshot', OLD.cliente_nombre_snapshot,
                'cliente_documento_snapshot', OLD.cliente_documento_snapshot,
                'deposito_codigo', OLD.deposito_codigo,
                'condicion_codigo', OLD.condicion_codigo,
                'transporte_codigo', OLD.transporte_codigo,
                'lista_codigo', OLD.lista_codigo,
                'vendedor_codigo', OLD.vendedor_codigo,
                'subtotal', OLD.subtotal,
                'descuento_total', OLD.descuento_total,
                'impuestos_total', OLD.impuestos_total,
                'total', OLD.total,
                'estado', OLD.estado,
                'cotizacion', OLD.cotizacion,
                'tango_sync_status', OLD.tango_sync_status,
                'nro_comprobante_tango', OLD.nro_comprobante_tango,
                'tango_sync_date', OLD.tango_sync_date,
                'tango_sync_log', OLD.tango_sync_log,
                'usuario_id', OLD.usuario_id,
                'usuario_nombre', OLD.usuario_nombre,
                'comentarios', OLD.comentarios,
                'observaciones', OLD.observaciones,
                'proximo_contacto', OLD.proximo_contacto,
                'vigencia', OLD.vigencia,
                'created_at', OLD.created_at,
                'updated_at', OLD.updated_at,
                'deleted_at', OLD.deleted_at
            ),
            @audit_user_id,
            @audit_user_name
        )
    ");

    // 3) Vista RxnLive
    $db->exec("DROP VIEW IF EXISTS RXN_LIVE_VW_PRESUPUESTOS_DELETES");

    $db->exec("
        CREATE VIEW RXN_LIVE_VW_PRESUPUESTOS_DELETES AS
        SELECT
            a.id,
            a.empresa_id,
            a.presupuesto_id,
            a.numero,
            a.fecha,
            a.cliente_nombre AS cliente,
            a.total,
            a.estado,
            a.tango_sync_status,
            a.nro_comprobante_tango,
            a.tango_sync_date,
            CASE WHEN a.nro_comprobante_tango IS NOT NULL AND a.nro_comprobante_tango <> ''
                 THEN 'Sí — quedó huérfano en Tango'
                 ELSE 'No'
            END AS estaba_en_tango,
            a.tratativa_id,
            a.version_numero,
            a.usuario_id,
            a.usuario_nombre AS vendedor,
            a.comentarios,
            a.observaciones,
            a.deleted_by,
            a.deleted_by_nombre AS eliminado_por,
            a.deleted_at,
            1 AS cantidad
        FROM crm_presupuestos_audit_deletes a
    ");
};
