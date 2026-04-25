<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Tabla de horario laboral declarado por cada usuario (orientativo).
    //
    // Filas múltiples por (usuario_id, dia_semana) → permite bloques múltiples
    //   (ej: turno mañana 9-13 + turno tarde 14-18).
    // dia_semana: 1 (Lunes) ... 7 (Domingo) — convención ISO 8601.
    // bloque_inicio / bloque_fin: TIME locales del usuario. Si fin < inicio, se
    //   asume que el bloque cruza medianoche (ej: 22:00→06:00).
    // activo: permite "pausar" un bloque sin borrarlo (ej: vacaciones).
    //
    // Sirve para:
    //   1) Disparar notif "no iniciaste turno" si activa el flag y pasaron N min
    //      desde el bloque_inicio sin abrir un crm_horas.
    //   2) Disparar notif "olvidaste cerrar" si pasaron N min de bloque_fin con
    //      crm_horas abierto del usuario.
    //   3) Reportes futuros de "horas trabajadas vs horas previstas".
    $db->exec("
    CREATE TABLE IF NOT EXISTS usuario_horario_laboral (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        dia_semana TINYINT UNSIGNED NOT NULL,
        bloque_inicio TIME NOT NULL,
        bloque_fin TIME NOT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_horario_usuario_dia (usuario_id, dia_semana, activo),
        CONSTRAINT chk_dia_semana CHECK (dia_semana BETWEEN 1 AND 7)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Flags de notificación por usuario — viven en la tabla usuarios para que
    // queden cerca del perfil y se accedan en el mismo SELECT que el resto.
    //
    // Idempotente: si ya existen, el ADD COLUMN explota y lo silenciamos. Al
    // hacer release a prod, el script de migración correrá una sola vez por
    // entorno gracias al tracker de migrations_applied.
    try {
        $db->exec("ALTER TABLE usuarios
            ADD COLUMN notif_no_iniciaste_activa TINYINT(1) NOT NULL DEFAULT 0
                COMMENT 'Si 1, dispara notif cuando pasa la hora de inicio del horario laboral sin abrir turno'");
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') === false) {
            throw $e;
        }
    }

    try {
        $db->exec("ALTER TABLE usuarios
            ADD COLUMN minutos_tolerancia_olvido INT NOT NULL DEFAULT 30
                COMMENT 'Minutos después de bloque_fin antes de disparar notif olvidaste cerrar (default 30)'");
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') === false) {
            throw $e;
        }
    }
};
