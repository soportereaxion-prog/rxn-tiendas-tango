<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Tabla crm_mail_job_items: cada destinatario individual de un envío masivo.
    // Se crea una fila por cada cliente que el reporte devolvió al momento de
    // disparar el job.
    //
    // recipient_data_json guarda el "snapshot" de las variables del destinatario
    // al momento del envío. Así, aunque la DB cambie después, n8n tiene los
    // datos que necesita para renderizar el template sin tener que volver a
    // consultar el reporte.
    //
    // tracking_token: string único usado en el pixel de apertura y en los
    // redirects de click. Se genera al crear el item y viaja dentro del HTML.
    //
    // Estados:
    //   pending → n8n aún no lo procesó
    //   sent    → enviado OK
    //   failed  → el SMTP rechazó el envío
    //   skipped → n8n decidió saltarlo (ej. dirección inválida antes de intentar)
    $db->exec("
    CREATE TABLE IF NOT EXISTS crm_mail_job_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        empresa_id INT NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        recipient_name VARCHAR(255) NULL,
        recipient_data_json TEXT NULL,
        tracking_token VARCHAR(64) NOT NULL,
        estado ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
        sent_at DATETIME NULL,
        error_msg TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_crm_mail_job_items_token (tracking_token),
        KEY idx_crm_mail_job_items_job (job_id),
        KEY idx_crm_mail_job_items_job_estado (job_id, estado),
        KEY idx_crm_mail_job_items_empresa (empresa_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
