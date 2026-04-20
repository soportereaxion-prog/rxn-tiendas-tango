<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Suma content_report_id a crm_mail_jobs. Es OPCIONAL — cuando está presente,
    // el JobDispatcher lo usa para resolver un bloque HTML broadcast que se
    // inyecta en el body_snapshot reemplazando el placeholder {{Bloque.html}}.
    //
    // La columna se agrega de forma idempotente (chequeo INFORMATION_SCHEMA)
    // para no romper si la migración se corre dos veces en un entorno mixto.

    $col = $db->query("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'crm_mail_jobs'
          AND COLUMN_NAME = 'content_report_id'
    ")->fetchColumn();

    if ((int) $col === 0) {
        $db->exec("
            ALTER TABLE crm_mail_jobs
            ADD COLUMN content_report_id INT NULL DEFAULT NULL AFTER report_id,
            ADD KEY idx_crm_mail_jobs_content_report (content_report_id)
        ");
    }
};
