<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Tabla crm_mail_jobs: cabecera de cada envío masivo disparado.
    // Es el "trabajo" completo que n8n procesa autónomamente.
    //
    // Flujo de estados:
    //   queued    → recién creado, esperando que n8n lo tome
    //   running   → n8n está procesando
    //   paused    → pausado por el usuario (v2, no en MVP)
    //   completed → terminó OK (puede tener fallidos individuales, pero el job cerró)
    //   cancelled → el usuario canceló mediante cancel_flag
    //   failed    → error irrecuperable (DB down, SMTP totalmente muerto, etc.)
    //
    // cancel_flag: n8n consulta este flag entre cada iteración del loop.
    //   Si el usuario clickea "Cancelar" desde el CRM, se pone en 1 y n8n corta.
    //
    // body_snapshot: guardamos el HTML renderizado al momento del envío para
    // tener trazabilidad aunque la plantilla original se modifique después.
    $db->exec("
    CREATE TABLE IF NOT EXISTS crm_mail_jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        usuario_id INT NOT NULL,
        report_id INT NULL,
        template_id INT NULL,
        smtp_config_id INT NOT NULL,
        asunto VARCHAR(255) NOT NULL,
        body_snapshot LONGTEXT NOT NULL,
        attachments_json TEXT NULL,
        estado ENUM('queued','running','paused','completed','cancelled','failed') NOT NULL DEFAULT 'queued',
        cancel_flag TINYINT(1) NOT NULL DEFAULT 0,
        total_destinatarios INT NOT NULL DEFAULT 0,
        total_enviados INT NOT NULL DEFAULT 0,
        total_fallidos INT NOT NULL DEFAULT 0,
        total_skipped INT NOT NULL DEFAULT 0,
        mensaje_error TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        started_at DATETIME NULL,
        finished_at DATETIME NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_crm_mail_jobs_empresa (empresa_id),
        KEY idx_crm_mail_jobs_usuario (empresa_id, usuario_id),
        KEY idx_crm_mail_jobs_estado (empresa_id, estado),
        KEY idx_crm_mail_jobs_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
