<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Tabla crm_mail_templates: plantillas HTML reutilizables para envíos masivos.
    // Se asocian OPCIONALMENTE a un reporte (report_id) para conocer qué variables
    // están disponibles. Cuando el usuario edita una plantilla, el editor WYSIWYG
    // le ofrece las variables del reporte asociado.
    //
    // body_html guarda HTML crudo con placeholders tipo {{variable}} que se
    // reemplazan al momento del envío con los valores de cada destinatario.
    //
    // available_vars_json es un snapshot de las variables disponibles al momento
    // de guardar la plantilla (útil para validar antes de enviar).
    $db->exec("
    CREATE TABLE IF NOT EXISTS crm_mail_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        nombre VARCHAR(180) NOT NULL,
        descripcion TEXT NULL,
        report_id INT NULL,
        asunto VARCHAR(255) NOT NULL,
        body_html LONGTEXT NOT NULL,
        available_vars_json TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL DEFAULT NULL,
        UNIQUE KEY uk_crm_mail_templates_empresa_nombre (empresa_id, nombre),
        KEY idx_crm_mail_templates_empresa (empresa_id),
        KEY idx_crm_mail_templates_report (report_id),
        KEY idx_crm_mail_templates_deleted (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
