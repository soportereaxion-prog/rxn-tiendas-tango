<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Versionado de presupuestos (release 1.29.x).
 *
 * Suma 2 columnas a crm_presupuestos:
 *   - version_padre_id INT NULL  → apunta al presupuesto raíz del grupo de versiones.
 *                                  NULL = es la raíz (v1). Si tiene valor, esta fila
 *                                  es una versión derivada.
 *                                  Se usa el ID de la RAÍZ (no del padre directo) para
 *                                  tener un árbol "plano" simple de queryar.
 *   - version_numero INT NOT NULL DEFAULT 1
 *                                  → versión secuencial dentro del grupo.
 *                                  La raíz es 1, las derivadas son 2, 3, etc.
 *
 * Botón "Nueva versión" en el form crea una copia con numero secuencial NUEVO,
 * version_padre_id = ID_de_la_raiz, version_numero = max(grupo) + 1.
 *
 * Idempotente: chequea SHOW COLUMNS antes de cada ALTER.
 */
return function (): void {
    $db = Database::getConnection();

    $stmt = $db->query("SHOW COLUMNS FROM crm_presupuestos LIKE 'version_padre_id'");
    if (!$stmt->fetch()) {
        $db->exec('ALTER TABLE crm_presupuestos
            ADD COLUMN version_padre_id INT NULL AFTER tratativa_id,
            ADD COLUMN version_numero INT NOT NULL DEFAULT 1 AFTER version_padre_id');
    }

    // Índice para queryar el grupo de versiones rápido (todos los hijos de una raíz).
    $idxStmt = $db->query("SHOW INDEX FROM crm_presupuestos WHERE Key_name = 'idx_crm_presupuestos_version_padre'");
    if (!$idxStmt->fetch()) {
        $db->exec('CREATE INDEX idx_crm_presupuestos_version_padre ON crm_presupuestos (empresa_id, version_padre_id)');
    }
};
