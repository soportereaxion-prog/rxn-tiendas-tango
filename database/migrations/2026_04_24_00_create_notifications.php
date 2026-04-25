<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Tabla de notificaciones in-app — sirve a TODA la suite, no solo a un módulo.
    //
    // empresa_id + usuario_id: destinatario. Toda query filtra por estos dos para
    //   garantizar aislamiento multi-tenant + privacidad por usuario.
    // type: clave estable para identificar el origen funcional de la notificación
    //   (ej: 'crm_horas.turno_olvidado', 'crm_horas.no_iniciaste',
    //   'crm_horas.ajuste_admin', 'sistema.broadcast'). Permite filtros por tipo
    //   y futura preferencia de "silenciar tipo X" por usuario.
    // title: texto corto visible en el dropdown. body: HTML opcional con detalle.
    // link: URL relativa a la que el click navega (ej: /mi-empresa/crm/horas/123).
    // data: JSON libre con metadata (ej: {"hora_id":123, "old_start":"...", "new_start":"..."})
    //   para enriquecer la UI sin tener que joinear con la entidad.
    // read_at: NULL = no leída. Cuando el usuario abre el dropdown o entra al
    //   listado, se marcan como leídas en bloque o individualmente.
    // deleted_at: soft-delete. Por decisión del rey: las notificaciones NO se
    //   borran solas — quedan como registro histórico. El soft-delete existe
    //   solo para casos extremos (ej: notificación generada por bug).
    $db->exec("
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        usuario_id INT NOT NULL,
        type VARCHAR(80) NOT NULL,
        title VARCHAR(255) NOT NULL,
        body TEXT NULL,
        link VARCHAR(500) NULL,
        data JSON NULL,
        read_at DATETIME NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL DEFAULT NULL,
        KEY idx_notif_inbox (empresa_id, usuario_id, deleted_at, read_at, created_at),
        KEY idx_notif_type (empresa_id, usuario_id, type, deleted_at),
        CONSTRAINT fk_notif_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
