<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Repair backfill: el response de Create?process=19845 de Tango Connect devuelve el
 * ID del pedido creado en `$.data.savedId` (int escalar), NO en `$.data.value.ID_GVA21`
 * como asumió la migración inicial 2026_04_18_add_tango_estado_to_crm_pedidos_servicio.
 *
 * Resultado: los 26 PDS históricos quedaron con tango_id_gva21 NULL pese a estar enviados
 * a Tango con éxito. Esta migración reintenta el backfill con el path correcto.
 *
 * También setea tango_estado=2 (Aprobado por default) y tango_estado_sync_at=NOW() para
 * que el tab los liste como "recién sincronizados sin pull todavía". El pull real se hace
 * desde RxnSync con el botón "Sincronizar Estados de Pedidos".
 */
return function (): void {
    $db = Database::getConnection();

    $stmt = $db->query("SHOW TABLES LIKE 'crm_pedidos_servicio'");
    if (!$stmt->fetch()) {
        return;
    }

    // MariaDB no soporta CAST('null' AS JSON) → usamos JSON_UNQUOTE + NULLIF(..., 'null').
    $db->exec(<<<SQL
        UPDATE crm_pedidos_servicio
        SET tango_id_gva21 = COALESCE(
                tango_id_gva21,
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(tango_sync_response, '$.data.savedId')), 'null')
            ),
            tango_estado = COALESCE(tango_estado, 2),
            tango_estado_sync_at = COALESCE(tango_estado_sync_at, NOW())
        WHERE tango_sync_status = 'success'
          AND tango_sync_response IS NOT NULL
          AND tango_id_gva21 IS NULL
    SQL);
};
