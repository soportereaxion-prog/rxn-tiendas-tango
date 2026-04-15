<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Tabla crm_mail_tracking_events: eventos de apertura y click.
    //
    // Flujo:
    //   - Al renderizar el HTML del mail, se inyecta:
    //       * un pixel <img src="/pixel/{tracking_token}.gif"> (apertura)
    //       * cada link <a href="..."> se reescribe a "/r/{tracking_token}?u={url_original_encoded}"
    //   - El endpoint correspondiente registra el evento en esta tabla
    //
    // NOTA: para MVP se registran 'open' y 'click'. El tipo 'bounce' queda
    // reservado para v2 (requiere parser de respuestas SMTP, más complejo).
    //
    // user_agent e ip se guardan para posible análisis futuro (ej. detectar
    // que un mismo mail fue abierto desde varios dispositivos).
    $db->exec("
    CREATE TABLE IF NOT EXISTS crm_mail_tracking_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_item_id INT NOT NULL,
        empresa_id INT NOT NULL,
        tipo ENUM('open','click','bounce') NOT NULL,
        url_clicked VARCHAR(1024) NULL,
        ip VARCHAR(45) NULL,
        user_agent VARCHAR(500) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_crm_mail_tracking_events_item (job_item_id),
        KEY idx_crm_mail_tracking_events_item_tipo (job_item_id, tipo),
        KEY idx_crm_mail_tracking_events_empresa (empresa_id),
        KEY idx_crm_mail_tracking_events_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
