<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Tabla `drafts` — borradores autoguardados de formularios largos.
 *
 * El PDS y (próximamente) Presupuestos hacen autosave debounced del estado del
 * form. Si la sesión muere, hay un crash de browser, o el usuario navega afuera
 * sin querer, al volver al form se ofrece retomar el borrador.
 *
 * Aislamiento: (user_id, empresa_id, modulo, ref_key). ref_key='new' para
 * formularios de creación, o el id del registro existente al editar.
 *
 * Concurrencia: last-write-wins. Si el mismo PDS está abierto en 2 tabs,
 * gana el último save. No vale la pena complicarla con merge.
 */
return function (): void {
    $db = Database::getConnection();

    $stmt = $db->query("SHOW TABLES LIKE 'drafts'");
    if ($stmt->fetch()) {
        return;
    }

    $db->exec(
        "CREATE TABLE drafts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            empresa_id INT NOT NULL,
            modulo VARCHAR(64) NOT NULL,
            ref_key VARCHAR(64) NOT NULL DEFAULT 'new',
            payload_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_user_modulo_ref (user_id, empresa_id, modulo, ref_key),
            KEY idx_user_updated (user_id, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
