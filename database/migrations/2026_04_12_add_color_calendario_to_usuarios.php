<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Color fijo de calendario por usuario (hex, ej: #007bff).
    // Se usa en la agenda CRM como fondo del evento y como pill de filtro.
    $stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE 'color_calendario'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN color_calendario VARCHAR(20) NULL DEFAULT '#007bff' AFTER preferencia_fuente");
    }
};
