<?php

/**
 * Migración: 2026_04_03_create_print_form_tables
 *
 * Crea las tablas base del motor de formularios de impresión canvas (PrintForms).
 * Incluye las tres tablas que componen el sistema: definiciones, versiones y assets.
 *
 * Idempotente: usa CREATE TABLE IF NOT EXISTS y ALTER TABLE … IF NOT EXISTS
 * para ser segura tanto en instalaciones nuevas como en actualizaciones.
 */

return function (\PDO $pdo): void {

    // ─── 1. DEFINICIONES ──────────────────────────────────────────────────────
    // Catálogo de formularios por empresa y clave de documento.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS print_form_definitions (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            empresa_id          BIGINT UNSIGNED NOT NULL,
            document_key        VARCHAR(80)     NOT NULL,
            nombre              VARCHAR(150)    NOT NULL,
            descripcion         VARCHAR(255)    NULL,
            estado              VARCHAR(20)     NOT NULL DEFAULT 'activo',
            version_activa_id   BIGINT UNSIGNED NULL,
            created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_print_form_empresa_document_key_nombre (empresa_id, document_key, nombre),
            KEY idx_print_form_empresa_document_key (empresa_id, document_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // ─── 2. VERSIONES ─────────────────────────────────────────────────────────
    // Cada versión guarda el JSON del canvas (objetos, page_config, fonts) y el asset de fondo.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS print_form_versions (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_definition_id  BIGINT UNSIGNED NOT NULL,
            version             INT UNSIGNED    NOT NULL,
            page_config_json    LONGTEXT        NOT NULL,
            objects_json        LONGTEXT        NOT NULL,
            fonts_json          LONGTEXT        NULL,
            background_asset_id BIGINT UNSIGNED NULL,
            notes               TEXT            NULL,
            created_by          BIGINT UNSIGNED NULL,
            created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_print_form_version (form_definition_id, version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // ─── 3. ASSETS ────────────────────────────────────────────────────────────
    // Archivos de fondo (PNG, JPG, WEBP) subidos desde el editor.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS print_form_assets (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            empresa_id      BIGINT UNSIGNED NOT NULL,
            tipo            VARCHAR(30)     NOT NULL,
            nombre_original VARCHAR(255)    NOT NULL,
            ruta            VARCHAR(255)    NOT NULL,
            mime_type       VARCHAR(120)    NOT NULL,
            tamano          BIGINT UNSIGNED NOT NULL DEFAULT 0,
            metadata_json   LONGTEXT        NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_print_form_assets_empresa_tipo (empresa_id, tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
};
