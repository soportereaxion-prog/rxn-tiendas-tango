<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Tabla de eventos de agenda del CRM.
    // Sourcing polimorfico: origen_tipo + origen_id permiten que los eventos provengan
    // de PDS, Presupuestos, Tratativas, Llamadas o sean eventos manuales puros.
    // Cada evento puede tener un google_event_id si fue sincronizado con Google Calendar
    // (push-only en Fase 2).
    $db->exec("
    CREATE TABLE IF NOT EXISTS crm_agenda_eventos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        usuario_id INT NULL,
        usuario_nombre VARCHAR(180) NULL,
        titulo VARCHAR(200) NOT NULL,
        descripcion TEXT NULL,
        ubicacion VARCHAR(255) NULL,
        inicio DATETIME NOT NULL,
        fin DATETIME NOT NULL,
        all_day TINYINT(1) NOT NULL DEFAULT 0,
        color VARCHAR(20) NULL,
        estado ENUM('programado','en_curso','completado','cancelado') NOT NULL DEFAULT 'programado',
        origen_tipo ENUM('manual','pds','presupuesto','tratativa','llamada','tratativa_accion') NOT NULL DEFAULT 'manual',
        origen_id INT NULL,
        google_event_id VARCHAR(100) NULL,
        google_calendar_id VARCHAR(255) NULL,
        synced_at DATETIME NULL,
        sync_error TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL DEFAULT NULL,
        KEY idx_crm_agenda_empresa_inicio (empresa_id, inicio),
        KEY idx_crm_agenda_empresa_usuario (empresa_id, usuario_id),
        KEY idx_crm_agenda_origen (empresa_id, origen_tipo, origen_id),
        KEY idx_crm_agenda_google (empresa_id, google_event_id),
        KEY idx_crm_agenda_deleted (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
