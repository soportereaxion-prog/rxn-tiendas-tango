<?php

/**
 * Migración: 2026_04_05_fix_rxn_live_vw_clientes
 *
 * Recrea la vista RXN_LIVE_VW_CLIENTES forzando la versión actualizada
 * que incluye las columnas 'estado', 'fecha_registro' y 'cantidad'.
 * 
 * Contexto: La migración original (2026_04_04_create_rxn_live_views) quedó
 * marcada como aplicada sin que la vista en BD tuviera estas columnas,
 * provocando que el gráfico analítico de Clientes no pudiera renderizar.
 */

return function (\PDO $pdo): void {

    // Forzar recreación de la vista con todas las columnas requeridas por el módulo RXN_LIVE
    $pdo->exec("
        CREATE OR REPLACE VIEW RXN_LIVE_VW_CLIENTES AS
        SELECT 
            id,
            empresa_id,
            razon_social,
            documento,
            email,
            telefono,
            CASE WHEN activo = 1 THEN 'Activo' ELSE 'Inactivo' END AS estado,
            DATE(created_at) AS fecha_registro,
            1 AS cantidad
        FROM crm_clientes
        WHERE deleted_at IS NULL
    ");
};
