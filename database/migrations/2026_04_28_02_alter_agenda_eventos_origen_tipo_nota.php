<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Extiende el ENUM origen_tipo de crm_agenda_eventos para aceptar 'nota'.
 *
 * Sin esta migración, el INSERT del proyector de notas falla silenciosamente
 * porque MySQL rechaza el valor fuera del ENUM. La columna ya existía con un
 * ENUM cerrado desde la creación de la tabla (release 1.x.x), así que cada
 * vez que sumamos un origen nuevo hay que extenderla.
 *
 * Idempotente: chequea el COLUMN_TYPE antes de alterar.
 */
return function (): void {
    $db = Database::getConnection();

    $stmt = $db->query("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'crm_agenda_eventos'
          AND COLUMN_NAME = 'origen_tipo'
        LIMIT 1
    ");
    $columnType = (string) ($stmt->fetchColumn() ?: '');

    if ($columnType === '' || str_contains($columnType, "'nota'")) {
        return;
    }

    $db->exec("
        ALTER TABLE crm_agenda_eventos
        MODIFY COLUMN origen_tipo
        ENUM('manual','pds','presupuesto','tratativa','llamada','tratativa_accion','hora','nota')
        NOT NULL DEFAULT 'manual'
    ");
};
