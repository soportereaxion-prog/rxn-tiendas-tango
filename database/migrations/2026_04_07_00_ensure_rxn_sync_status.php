<?php

declare(strict_types=1);

/**
 * Ensure rxn_sync_status + rxn_sync_log existen con el schema COMPLETO.
 *
 * --- POR QUÉ EXISTE ESTA MIGRACIÓN ---
 *
 * El MigrationRunner ordena los archivos de database/migrations/ alfabéticamente
 * (sort() en MigrationRunner::getAvailableMigrations()). El orden de las migraciones
 * previas generaba un bug recurrente:
 *
 *   1. 2026_04_07_01_rxn_sync_consolidation.php  ("01_" < "crear_" en ASCII)
 *      → intenta SHOW COLUMNS FROM rxn_sync_status antes de que exista
 *      → falla con SQLSTATE[42S02] Table 'rxn_suite_core.rxn_sync_status' doesn't exist
 *      → MigrationRunner hace break y corta la cadena
 *   2. 2026_04_07_crear_rxn_sync_status.php      (nunca llega a correr)
 *
 * Esta migración se llama 2026_04_07_00_ensure_rxn_sync_status.php para ir
 * alfabéticamente ANTES de la consolidation ("00_" < "01_"), y garantiza que
 * rxn_sync_status exista con TODAS las columnas (incluyendo las que agregaba la
 * consolidation) antes de que cualquier otra migración toque la tabla.
 *
 * --- IDEMPOTENCIA ---
 *
 * - CREATE TABLE IF NOT EXISTS: no rompe si la tabla ya existe
 * - SHOW COLUMNS guards: agrega columnas solo si faltan (para bases legacy
 *   donde la tabla existe con schema parcial)
 * - Puede ejecutarse N veces sin efectos secundarios
 *
 * --- EFECTO SOBRE LAS OTRAS MIGRACIONES ---
 *
 * Después de que esta migración corra:
 * - 2026_04_07_01_rxn_sync_consolidation.php: SHOW COLUMNS encuentra todas las
 *   columnas (ya las agregó esta), salta los ALTER. CREATE TABLE IF NOT EXISTS
 *   para rxn_sync_log es no-op. Se marca SUCCESS.
 * - 2026_04_07_crear_rxn_sync_status.php: CREATE TABLE IF NOT EXISTS es no-op.
 *   Se marca SUCCESS.
 */

return function (): void {
    $db = \App\Core\Database::getConnection();

    // 1. Crear rxn_sync_status con el schema FINAL completo (incluye columnas de la consolidation).
    $db->exec("
        CREATE TABLE IF NOT EXISTS rxn_sync_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            entidad VARCHAR(50) NOT NULL COMMENT 'cliente, articulo',
            local_id INT NULL COMMENT 'ID de la tabla local',
            tango_id INT NULL COMMENT 'ID de Tango',
            estado ENUM('vinculado', 'pendiente', 'conflicto', 'error') NOT NULL DEFAULT 'pendiente',
            direccion_ultima_sync ENUM('push', 'pull', 'link', 'none') NOT NULL DEFAULT 'none',
            resultado_ultima_sync ENUM('ok', 'error', 'pending') NOT NULL DEFAULT 'pending',
            mensaje_error TEXT NULL,
            fecha_ultima_sync DATETIME NULL,
            fecha_ultimo_push DATETIME NULL,
            fecha_ultimo_pull DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_empresa_entidad_local (empresa_id, entidad, local_id),
            INDEX idx_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 2. Edge case: si la tabla ya existía con un schema parcial (legacy),
    //    asegurar que todas las columnas adicionales de la consolidation estén presentes.
    //    Esto cubre el caso donde la tabla fue creada antes por otra migración anterior
    //    sin las columnas de dirección/resultado/fechas push-pull.
    $columnsToEnsure = [
        'direccion_ultima_sync' => [
            'type' => "ENUM('push', 'pull', 'link', 'none') NOT NULL DEFAULT 'none'",
            'after' => 'estado',
        ],
        'resultado_ultima_sync' => [
            'type' => "ENUM('ok', 'error', 'pending') NOT NULL DEFAULT 'pending'",
            'after' => 'direccion_ultima_sync',
        ],
        'fecha_ultimo_push' => [
            'type' => 'DATETIME NULL',
            'after' => 'fecha_ultima_sync',
        ],
        'fecha_ultimo_pull' => [
            'type' => 'DATETIME NULL',
            'after' => 'fecha_ultimo_push',
        ],
    ];

    foreach ($columnsToEnsure as $columnName => $spec) {
        $stmt = $db->query("SHOW COLUMNS FROM rxn_sync_status LIKE " . $db->quote($columnName));
        if ($stmt && $stmt->rowCount() === 0) {
            $db->exec("ALTER TABLE rxn_sync_status ADD COLUMN {$columnName} {$spec['type']} AFTER {$spec['after']}");
        }
    }

    // 3. Crear rxn_sync_log (idempotente). La consolidation también la crea pero al final de su script;
    //    si la consolidation corre primero, esta tabla ya está acá.
    $db->exec("
        CREATE TABLE IF NOT EXISTS rxn_sync_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            entidad VARCHAR(50) NOT NULL COMMENT 'cliente, articulo',
            local_id INT NULL,
            tango_id INT NULL,
            direccion ENUM('push', 'pull', 'link') NOT NULL,
            resultado ENUM('ok', 'error') NOT NULL,
            mensaje TEXT NULL,
            payload_resumen JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_empresa_entidad (empresa_id, entidad),
            INDEX idx_local_id (local_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
};
