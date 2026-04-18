<?php

/**
 * Migración: 2026_04_18_update_rxn_live_vw_pds_tango_estado
 *
 * Extiende la vista de Pedidos de Servicio (Tiempos) de RXN Live para incluir la
 * información de sincronización de estado con Tango que agregamos en release 1.15.0:
 *
 *  - tango_estado       (INT 2=Aprobado, 3=Cumplido, 4=Cerrado, 5=Anulado)
 *  - tango_estado_label (VARCHAR legible, resuelto vía CASE en SQL)
 *  - tango_estado_sync_at (DATETIME de última sincronización)
 *
 * También cambia `nro_pedido_tango` para priorizar el formato legible que trae el
 * sync de Tango (ej: "X00652-00000964") por sobre el ID crudo histórico. Si no hay
 * tango_nro_pedido, cae al nro_pedido legacy como antes.
 *
 * La vista se usa desde módulos Data Live / Pivot del CRM.
 */

return function (\PDO $pdo): void {

    $pdo->exec("
        CREATE OR REPLACE VIEW RXN_LIVE_VW_PEDIDOS_SERVICIO AS
        SELECT
            ps.empresa_id,
            ps.cliente_nombre as razon_social,
            ps.articulo_codigo as codigo_articulo,
            ps.articulo_nombre as articulo_factura,
            ps.cliente_id,
            ps.numero as id_pedidoservicio,
            DATE(ps.fecha_inicio) as fecha,
            ps.solicito as solicitante,
            ps.usuario_id as id_tecnico,
            ps.usuario_nombre as usuario,
            REPLACE(REPLACE(ps.diagnostico, '\\r', ' '), '\\n', ' ') as diagnostico,
            ps.tiempo_decimal as totalpds,
            c.codigo_tango as cod_tango,
            COALESCE(NULLIF(ps.tango_nro_pedido, ''), ps.nro_pedido) as nro_pedido_tango,
            ps.clasificacion_codigo as clasificacion,
            ps.tango_estado,
            CASE ps.tango_estado
                WHEN 2 THEN 'Aprobado'
                WHEN 3 THEN 'Cumplido'
                WHEN 4 THEN 'Cerrado'
                WHEN 5 THEN 'Anulado'
                ELSE 'Sin sync'
            END as tango_estado_label,
            ps.tango_estado_sync_at,
            1 as cantidad
        FROM crm_pedidos_servicio ps
        LEFT JOIN crm_clientes c ON ps.cliente_id = c.id
        WHERE ps.deleted_at IS NULL
    ");
};
