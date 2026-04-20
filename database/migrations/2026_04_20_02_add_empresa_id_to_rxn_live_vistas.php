<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Asegurar que la tabla exista (en algunos entornos recién se crea on-the-fly en
    // RxnLiveService::saveUserView(); si nadie guardó una vista todavía, podría faltar).
    $db->exec("
        CREATE TABLE IF NOT EXISTS rxn_live_vistas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            dataset VARCHAR(100) NOT NULL,
            nombre VARCHAR(150) NOT NULL,
            config JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_usuario_dataset (usuario_id, dataset)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Agregar empresa_id (idempotente). Las vistas pasan a compartirse dentro de la empresa:
    // todos los usuarios de la misma empresa_id ven las mismas vistas (scope read = empresa).
    // El ownership para edit/delete sigue siendo usuario_id (solo el dueño modifica la suya).
    $stmt = $db->query("SHOW COLUMNS FROM rxn_live_vistas LIKE 'empresa_id'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE rxn_live_vistas ADD COLUMN empresa_id INT NULL DEFAULT NULL AFTER usuario_id");

        // Backfill: traer empresa_id desde usuarios para registros existentes.
        $db->exec("
            UPDATE rxn_live_vistas v
            INNER JOIN usuarios u ON u.id = v.usuario_id
               SET v.empresa_id = u.empresa_id
             WHERE v.empresa_id IS NULL
        ");

        // Índice para listados por empresa+dataset (reemplaza semánticamente al viejo por usuario+dataset,
        // que se mantiene para no romper nada y porque delete-by-owner sigue usando usuario_id).
        $db->exec("ALTER TABLE rxn_live_vistas ADD INDEX idx_empresa_dataset (empresa_id, dataset)");
    }
};
