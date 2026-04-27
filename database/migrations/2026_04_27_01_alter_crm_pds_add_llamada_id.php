<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // 1. Columna en crm_pedidos_servicio
    $stmt = $db->query("SHOW COLUMNS FROM crm_pedidos_servicio LIKE 'llamada_id'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_pedidos_servicio ADD COLUMN llamada_id INT NULL AFTER tratativa_id");
    }

    // 2. Índice — facilita LEFT JOIN desde el listado de llamadas
    $idxCheck = $db->query("SHOW INDEX FROM crm_pedidos_servicio WHERE Key_name = 'idx_pds_llamada'");
    if (!$idxCheck->fetch()) {
        $db->exec("CREATE INDEX idx_pds_llamada ON crm_pedidos_servicio (llamada_id)");
    }

    // 3. Backfill heurístico: matchea PDS con llamadas del MISMO cliente
    //    cuya hora de fin (fecha + duracion) está dentro de las 2hs previas
    //    a la fecha_inicio del PDS. Solo actualiza PDS que todavía no tienen
    //    llamada_id seteado, y elige la llamada más cercana en el tiempo.
    //
    //    Razonable porque: (a) el flujo actual abre el PDS apenas termina la
    //    llamada, (b) misma empresa + mismo cliente + ventana corta ≈ alta
    //    probabilidad de que sea la llamada que originó el PDS.
    //
    //    Idempotente: el WHERE p.llamada_id IS NULL evita pisar matches previos.
    $db->exec("
        UPDATE crm_pedidos_servicio p
        INNER JOIN (
            SELECT
                p2.id AS pds_id,
                (
                    SELECT l.id
                    FROM crm_llamadas l
                    WHERE l.empresa_id = p2.empresa_id
                      AND l.cliente_id = p2.cliente_id
                      AND l.cliente_id IS NOT NULL
                      AND l.deleted_at IS NULL
                      AND l.fecha IS NOT NULL
                      AND DATE_ADD(l.fecha, INTERVAL COALESCE(CAST(l.duracion AS UNSIGNED), 0) SECOND)
                          BETWEEN DATE_SUB(p2.fecha_inicio, INTERVAL 2 HOUR) AND p2.fecha_inicio
                    ORDER BY ABS(TIMESTAMPDIFF(SECOND,
                                  DATE_ADD(l.fecha, INTERVAL COALESCE(CAST(l.duracion AS UNSIGNED), 0) SECOND),
                                  p2.fecha_inicio)) ASC
                    LIMIT 1
                ) AS matched_llamada_id
            FROM crm_pedidos_servicio p2
            WHERE p2.llamada_id IS NULL
              AND p2.cliente_id IS NOT NULL
              AND p2.fecha_inicio IS NOT NULL
              AND p2.deleted_at IS NULL
        ) m ON m.pds_id = p.id
        SET p.llamada_id = m.matched_llamada_id
        WHERE m.matched_llamada_id IS NOT NULL
          AND p.llamada_id IS NULL
    ");
};
