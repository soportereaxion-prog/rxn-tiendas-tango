<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Agrega el campo titulo_pestana a la tabla empresas
 */
return function (): void {
    $db = Database::getConnection();

    // Validar si la columna existe antes de intentar agregarla
    $stmt = $db->query("SHOW COLUMNS FROM empresas LIKE 'titulo_pestana'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE empresas ADD COLUMN titulo_pestana VARCHAR(100) NULL AFTER nombre");
    }
};
