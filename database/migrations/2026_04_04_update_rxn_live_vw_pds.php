<?php

/**
 * Migración: 2026_04_04_update_rxn_live_vw_pds
 *
 * Actualiza la vista de Pedidos de Servicio (Tiempos) para incluir el código de artículo Tango
 * (codigo_articulo), estandarizar el nombre de la columna "usuario" para hacerlo consistente con el CRM,
 * y asegurar la disponibilidad del "nro_pedido_tango" para el módulo Data Live / Pivot.
 */

return function (\PDO $pdo): void {

    // 3. Vista de Pedidos de Servicio (CRM) - Actualización
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
            REPLACE(REPLACE(ps.diagnostico, '\r', ' '), '\n', ' ') as diagnostico,
            ps.tiempo_decimal as totalpds,
            c.codigo_tango as cod_tango,
            ps.nro_pedido as nro_pedido_tango,
            ps.clasificacion_codigo as clasificacion,
            1 as cantidad
        FROM crm_pedidos_servicio ps
        LEFT JOIN crm_clientes c ON ps.cliente_id = c.id
        WHERE ps.deleted_at IS NULL
    ");
};
