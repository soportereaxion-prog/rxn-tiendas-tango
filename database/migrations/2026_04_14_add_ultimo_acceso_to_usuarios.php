<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Agrega `usuarios.ultimo_acceso` (TIMESTAMP NULL) para detectar presencia online.
 *
 * Uso: el middleware de sesión en App.php actualiza esta columna en cada request
 * autenticado (con throttle de 60s para no martillar la DB). El módulo CRM de
 * Monitoreo de Operadores lee esta columna para mostrar el indicador verde de
 * "en línea" sobre el avatar cuando el last_activity fue dentro de los últimos
 * ONLINE_THRESHOLD minutos (default 5).
 *
 * Idempotente — se puede correr N veces.
 */
return function (): void {
    $db = Database::getConnection();

    $stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE 'ultimo_acceso'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN ultimo_acceso TIMESTAMP NULL DEFAULT NULL AFTER color_calendario");
    }

    // Index para queries de presencia (ej: WHERE ultimo_acceso > NOW() - INTERVAL 5 MINUTE)
    $idxStmt = $db->query("SHOW INDEX FROM usuarios WHERE Key_name = 'idx_usuarios_ultimo_acceso'");
    if (!$idxStmt->fetch()) {
        $db->exec("ALTER TABLE usuarios ADD INDEX idx_usuarios_ultimo_acceso (ultimo_acceso)");
    }
};
