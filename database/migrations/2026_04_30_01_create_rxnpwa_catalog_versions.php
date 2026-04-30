<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Crea la tabla `rxnpwa_catalog_versions` que guarda el hash + metadata
 * del catálogo offline por empresa para la PWA mobile (Bloque A — Fase 1).
 *
 * 1 fila por empresa (UNIQUE empresa_id). El hash se invalida (NULL) desde
 * los syncs de artículos / clientes / catálogos comerciales, y se recalcula
 * la próxima vez que la app pega a /api/rxnpwa/catalog/version.
 */
return function (): void {
    $db = Database::getConnection();

    $db->exec("CREATE TABLE IF NOT EXISTS rxnpwa_catalog_versions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        empresa_id INT UNSIGNED NOT NULL,
        hash CHAR(40) NULL,
        generated_at DATETIME NULL,
        payload_size_bytes INT UNSIGNED NULL,
        payload_items_count INT UNSIGNED NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_rxnpwa_catalog_versions_empresa (empresa_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
