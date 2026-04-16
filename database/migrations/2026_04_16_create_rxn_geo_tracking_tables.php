<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Crea las 3 tablas del módulo RxnGeoTracking:
 *   - rxn_geo_eventos   → eventos de tracking (login + creación de PDS/Presupuestos/Tratativas)
 *   - rxn_geo_config    → configuración por empresa (habilitado, retención, versión consentimiento)
 *   - rxn_geo_consent   → historial de consentimiento del usuario (prueba legal Ley 25.326)
 *
 * Idempotente: usa CREATE TABLE IF NOT EXISTS para poder re-ejecutarse sin romper.
 *
 * Ver app/modules/RxnGeoTracking/MODULE_CONTEXT.md para el detalle arquitectónico.
 */
return function (): void {
    $db = Database::getConnection();

    // Tabla de eventos: cada login/creación queda registrada acá.
    // accuracy_source: 'ip' | 'gps' | 'wifi' | 'denied' | 'error'
    //   - 'ip'    → solo resolvimos por IP, no hubo GPS/WiFi
    //   - 'gps'   → Geolocation API devolvió posición de alta precisión
    //   - 'wifi'  → Geolocation API devolvió posición por triangulación WiFi/IP del browser
    //   - 'denied'→ el user rechazó el permiso en el browser
    //   - 'error' → el browser no pudo obtener posición (timeout, unavailable, etc.)
    $db->exec("
    CREATE TABLE IF NOT EXISTS rxn_geo_eventos (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        user_id INT NOT NULL,
        event_type VARCHAR(64) NOT NULL,
        entidad_tipo VARCHAR(32) NULL DEFAULT NULL,
        entidad_id BIGINT NULL DEFAULT NULL,
        ip_address VARCHAR(45) NULL DEFAULT NULL,
        lat DECIMAL(10,7) NULL DEFAULT NULL,
        lng DECIMAL(10,7) NULL DEFAULT NULL,
        accuracy_meters INT NULL DEFAULT NULL,
        accuracy_source VARCHAR(16) NOT NULL DEFAULT 'ip',
        resolved_city VARCHAR(128) NULL DEFAULT NULL,
        resolved_region VARCHAR(128) NULL DEFAULT NULL,
        resolved_country CHAR(2) NULL DEFAULT NULL,
        user_agent VARCHAR(512) NULL DEFAULT NULL,
        consent_version VARCHAR(16) NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_rxn_geo_eventos_empresa_created (empresa_id, created_at),
        KEY idx_rxn_geo_eventos_empresa_user_created (empresa_id, user_id, created_at),
        KEY idx_rxn_geo_eventos_event_type (event_type),
        KEY idx_rxn_geo_eventos_created_at (created_at),
        KEY idx_rxn_geo_eventos_entidad (entidad_tipo, entidad_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Config por empresa. PK simple por empresa_id (1:1).
    // retention_days: rango defensivo 30-730, default 90.
    // requires_gps: si es 1, el banner no ofrece "denegar" — o acepta GPS o cierra sesión.
    // consent_version_current: se bumpea cuando cambia la política de tracking.
    $db->exec("
    CREATE TABLE IF NOT EXISTS rxn_geo_config (
        empresa_id INT NOT NULL PRIMARY KEY,
        habilitado TINYINT(1) NOT NULL DEFAULT 1,
        retention_days INT NOT NULL DEFAULT 90,
        requires_gps TINYINT(1) NOT NULL DEFAULT 0,
        consent_version_current VARCHAR(16) NOT NULL DEFAULT 'v1',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Consentimiento: queda registro con IP + user-agent al momento de la decisión.
    // Índice único (user_id, empresa_id, consent_version) para evitar duplicados de
    // la misma versión pero permitir histórico cuando suba la versión.
    $db->exec("
    CREATE TABLE IF NOT EXISTS rxn_geo_consent (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        empresa_id INT NOT NULL,
        consent_version VARCHAR(16) NOT NULL,
        decision VARCHAR(16) NOT NULL,
        ip_address VARCHAR(45) NULL DEFAULT NULL,
        user_agent VARCHAR(512) NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_rxn_geo_consent_user_empresa_version (user_id, empresa_id, consent_version),
        KEY idx_rxn_geo_consent_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
