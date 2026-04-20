<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Tabla customer_notes: novedades/releases en lenguaje de usuario final, para
    // enviarse como contenido broadcast en un envío masivo del CRM.
    //
    // Es una tabla GLOBAL (sin empresa_id) — las novedades se redactan una sola
    // vez y pueden viajar a cualquier empresa destino desde el builder de reportes
    // del módulo CrmMailMasivos.
    //
    // category: clasifica la novedad para elegir color/ícono del bloque HTML.
    //   feature      → nueva capacidad del producto
    //   mejora       → mejora sobre una capacidad existente
    //   seguridad    → refuerzo de seguridad (lenguaje de CAPACIDAD, no de defecto)
    //   performance  → mejora de rendimiento
    //   fix_visible  → arreglo visible para el usuario final
    //
    // status: 'draft' mientras se redacta; 'published' cuando entra al universo
    // de lo que puede seleccionar un reporte de contenido.
    //
    // version_ref: opcional, liga la nota a una release interna (ej "1.16.1").
    // No se muestra al cliente — sirve para trazabilidad del equipo.
    $db->exec("
    CREATE TABLE IF NOT EXISTS customer_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(180) NOT NULL,
        body_html TEXT NOT NULL,
        category ENUM('feature','mejora','seguridad','performance','fix_visible') NOT NULL,
        version_ref VARCHAR(40) NULL,
        status ENUM('draft','published') NOT NULL DEFAULT 'draft',
        published_at DATETIME NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL DEFAULT NULL,
        KEY idx_customer_notes_status (status),
        KEY idx_customer_notes_category (category),
        KEY idx_customer_notes_published (published_at),
        KEY idx_customer_notes_deleted (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
