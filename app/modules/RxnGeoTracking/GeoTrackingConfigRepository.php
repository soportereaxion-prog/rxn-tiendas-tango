<?php

declare(strict_types=1);

namespace App\Modules\RxnGeoTracking;

use App\Core\Database;
use PDO;

/**
 * Configuración del módulo por empresa. 1:1 con la tabla rxn_geo_config.
 *
 * Siempre devuelve una config válida: si la empresa no tiene fila propia en rxn_geo_config,
 * se asumen los defaults (habilitado=1, retention_days=90, requires_gps=0, consent_version='v1').
 * Esto evita que tengamos que insertar una fila para cada empresa preventivamente.
 */
class GeoTrackingConfigRepository
{
    public const DEFAULT_RETENTION_DAYS = 90;
    public const MIN_RETENTION_DAYS = 30;
    public const MAX_RETENTION_DAYS = 730;
    public const DEFAULT_CONSENT_VERSION = 'v1';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Devuelve la config de la empresa con defaults aplicados.
     */
    public function getConfig(int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM rxn_geo_config WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([':empresa_id' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return self::defaultConfig($empresaId);
        }

        return [
            'empresa_id' => (int) $row['empresa_id'],
            'habilitado' => (int) $row['habilitado'] === 1,
            'retention_days' => (int) $row['retention_days'],
            'requires_gps' => (int) $row['requires_gps'] === 1,
            'consent_version_current' => (string) $row['consent_version_current'],
        ];
    }

    public function isEnabled(int $empresaId): bool
    {
        return (bool) $this->getConfig($empresaId)['habilitado'];
    }

    public function currentConsentVersion(int $empresaId): string
    {
        return (string) $this->getConfig($empresaId)['consent_version_current'];
    }

    /**
     * Upsert de la config. Usado por el dashboard admin (fase 4, pendiente).
     * Clampa retention_days al rango [MIN_RETENTION_DAYS, MAX_RETENTION_DAYS].
     */
    public function upsert(int $empresaId, array $fields): void
    {
        $current = $this->getConfig($empresaId);

        $habilitado = isset($fields['habilitado']) ? (bool) $fields['habilitado'] : $current['habilitado'];
        $retention = isset($fields['retention_days']) ? (int) $fields['retention_days'] : $current['retention_days'];
        $retention = max(self::MIN_RETENTION_DAYS, min(self::MAX_RETENTION_DAYS, $retention));
        $requiresGps = isset($fields['requires_gps']) ? (bool) $fields['requires_gps'] : $current['requires_gps'];
        $consentVersion = isset($fields['consent_version_current']) && is_string($fields['consent_version_current']) && $fields['consent_version_current'] !== ''
            ? $fields['consent_version_current']
            : $current['consent_version_current'];

        $stmt = $this->db->prepare('INSERT INTO rxn_geo_config (
                empresa_id, habilitado, retention_days, requires_gps, consent_version_current
            ) VALUES (
                :empresa_id, :habilitado, :retention_days, :requires_gps, :consent_version
            )
            ON DUPLICATE KEY UPDATE
                habilitado = VALUES(habilitado),
                retention_days = VALUES(retention_days),
                requires_gps = VALUES(requires_gps),
                consent_version_current = VALUES(consent_version_current)');

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':habilitado' => $habilitado ? 1 : 0,
            ':retention_days' => $retention,
            ':requires_gps' => $requiresGps ? 1 : 0,
            ':consent_version' => $consentVersion,
        ]);
    }

    private static function defaultConfig(int $empresaId): array
    {
        return [
            'empresa_id' => $empresaId,
            'habilitado' => true,
            'retention_days' => self::DEFAULT_RETENTION_DAYS,
            'requires_gps' => false,
            'consent_version_current' => self::DEFAULT_CONSENT_VERSION,
        ];
    }
}
