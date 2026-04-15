<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Tabla crm_mail_reports: reportes guardados del "diseñador Links" del módulo
    // de mail masivos. Cada reporte representa una selección visual de entidades,
    // campos y filtros (estilo Crystal Reports) que luego se usa como fuente de
    // destinatarios/variables para un envío masivo.
    //
    // config_json guarda la definición completa del diseño:
    //   - root_entity: entidad raíz (ej "CrmClientes")
    //   - relations: relaciones prendidas con sus alias
    //   - fields: campos seleccionados (por entidad) — se exponen como variables
    //   - filters: filtros visuales aplicados
    //   - mail_field: qué campo de la selección final es el destinatario
    $db->exec("
    CREATE TABLE IF NOT EXISTS crm_mail_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        nombre VARCHAR(180) NOT NULL,
        descripcion TEXT NULL,
        root_entity VARCHAR(80) NOT NULL,
        config_json LONGTEXT NOT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL DEFAULT NULL,
        UNIQUE KEY uk_crm_mail_reports_empresa_nombre (empresa_id, nombre),
        KEY idx_crm_mail_reports_empresa (empresa_id),
        KEY idx_crm_mail_reports_deleted (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
