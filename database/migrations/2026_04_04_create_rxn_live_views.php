<?php

/**
 * Migración: 2026_04_04_create_rxn_live_views
 *
 * Crea las vistas predefinidas de la base de datos para la ingesta del módulo RXN_LIVE.
 * Basado en el esquema de tablas existentes del sistema (crm_clientes, pedidos_web, clientes_web, crm_pedidos_servicio).
 */

return function (\PDO $pdo): void {

    // 1. Vista de Análisis de Clientes (CRM)
    $pdo->exec("
        CREATE OR REPLACE VIEW RXN_LIVE_VW_CLIENTES AS
        SELECT 
            id,
            empresa_id,
            razon_social,
            documento,
            email,
            telefono,
            CASE WHEN activo = 1 THEN 'Activo' ELSE 'Inactivo' END as estado,
            DATE(created_at) as fecha_registro,
            1 as cantidad
        FROM crm_clientes
        WHERE deleted_at IS NULL
    ");

    // 2. Vista de Resumen de Ventas Web
    $pdo->exec("
        CREATE OR REPLACE VIEW RXN_LIVE_VW_VENTAS AS
        SELECT 
            p.id as pedido_id,
            p.empresa_id,
            p.total,
            p.estado_tango as estado_sincronizacion,
            REPLACE(REPLACE(p.observaciones, '\r', ' '), '\n', ' ') as observaciones,
            TRIM(CONCAT(COALESCE(c.nombre, ''), ' ', COALESCE(c.apellido, ''))) as cliente_nombre,
            c.email as cliente_email,
            DATE(p.created_at) as fecha,
            1 as cantidad
        FROM pedidos_web p
        LEFT JOIN clientes_web c ON p.cliente_web_id = c.id
        WHERE p.activo = 1
    ");

    // 3. Vista de Pedidos de Servicio (CRM)
    $pdo->exec("
        CREATE OR REPLACE VIEW RXN_LIVE_VW_PEDIDOS_SERVICIO AS
        SELECT 
            ps.empresa_id,
            ps.cliente_nombre as razon_social,
            ps.articulo_nombre as articulo_factura,
            ps.cliente_id,
            ps.numero as id_pedidoservicio,
            DATE(ps.fecha_inicio) as fecha,
            ps.solicito as solicitante,
            ps.usuario_id as id_tecnico,
            ps.usuario_nombre as tecnico,
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
