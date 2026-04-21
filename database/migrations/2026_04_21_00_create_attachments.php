<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Tabla polimórfica de adjuntos.
    //
    // Sirve para cualquier módulo que necesite archivos asociados a una entidad
    // (crm_nota, crm_presupuesto, etc). El service (App\Core\Services\AttachmentService)
    // es el único punto de escritura/lectura: valida MIME contra whitelist, genera
    // filename seguro, persiste los metadatos y borra archivos físicos en delete.
    //
    // owner_type + owner_id: referencia lógica a la entidad dueña (no hay FK física
    // porque es polimórfica; la consistencia la mantiene el service via deleteByOwner()
    // llamado desde el delete del repo padre).
    //
    // path: ruta relativa desde la raíz del proyecto (ej: "public/uploads/empresas/7/attachments/2026/04/...").
    // Los archivos NO se sirven por URL directa — el endpoint /attachments/{id}/download
    // hace IDOR check antes de readfile() con Content-Disposition: attachment.
    //
    // deleted_at: soft-delete de metadatos. Los archivos físicos se borran sólo cuando
    // el owner hace forceDelete() o cuando se llama explícitamente a AttachmentService::delete().
    $db->exec("
    CREATE TABLE IF NOT EXISTS attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        owner_type VARCHAR(64) NOT NULL,
        owner_id INT NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        stored_name VARCHAR(255) NOT NULL,
        mime VARCHAR(128) NOT NULL,
        size_bytes BIGINT UNSIGNED NOT NULL,
        path VARCHAR(500) NOT NULL,
        uploaded_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL DEFAULT NULL,
        KEY idx_attachments_owner (empresa_id, owner_type, owner_id, deleted_at),
        KEY idx_attachments_deleted (deleted_at),
        CONSTRAINT fk_attachments_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
