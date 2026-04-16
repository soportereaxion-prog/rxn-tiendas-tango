<?php

declare(strict_types=1);

namespace App\Modules\RxnGeoTracking;

use App\Core\Database;
use PDO;

/**
 * Historial de consentimiento por usuario × empresa × versión.
 *
 * El índice único (user_id, empresa_id, consent_version) permite guardar histórico
 * cuando sube la versión pero impide duplicados dentro de la misma versión.
 *
 * Decisión de diseño: el registro guarda IP + user_agent al momento de la decisión
 * como prueba legal. Esto es especialmente importante para cumplir con la Ley 25.326
 * (obligación del responsable de poder demostrar el consentimiento).
 */
class GeoConsentRepository
{
    public const DECISION_ACCEPTED = 'accepted';
    public const DECISION_DENIED = 'denied';
    public const DECISION_LATER = 'later';

    public const VALID_DECISIONS = [
        self::DECISION_ACCEPTED,
        self::DECISION_DENIED,
        self::DECISION_LATER,
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Persiste o actualiza la decisión del usuario para una versión específica.
     * Si ya existe un registro (user, empresa, version), lo sobrescribe con la
     * nueva decisión y timestamp — útil cuando el user pasa de "later" a "accepted".
     */
    public function record(int $userId, int $empresaId, string $consentVersion, string $decision, ?string $ip, ?string $userAgent): void
    {
        if (!in_array($decision, self::VALID_DECISIONS, true)) {
            throw new \InvalidArgumentException('Decisión de consentimiento inválida: ' . $decision);
        }

        $stmt = $this->db->prepare('INSERT INTO rxn_geo_consent (
                user_id, empresa_id, consent_version, decision, ip_address, user_agent, created_at
            ) VALUES (
                :user_id, :empresa_id, :consent_version, :decision, :ip, :ua, NOW()
            )
            ON DUPLICATE KEY UPDATE
                decision = VALUES(decision),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                created_at = NOW()');

        $stmt->execute([
            ':user_id' => $userId,
            ':empresa_id' => $empresaId,
            ':consent_version' => $consentVersion,
            ':decision' => $decision,
            ':ip' => $ip,
            ':ua' => $userAgent,
        ]);
    }

    /**
     * Busca la decisión del usuario para una versión específica.
     * Devuelve null si nunca la respondió.
     */
    public function findDecision(int $userId, int $empresaId, string $consentVersion): ?string
    {
        $stmt = $this->db->prepare('SELECT decision FROM rxn_geo_consent
            WHERE user_id = :user_id
              AND empresa_id = :empresa_id
              AND consent_version = :consent_version
            LIMIT 1');
        $stmt->execute([
            ':user_id' => $userId,
            ':empresa_id' => $empresaId,
            ':consent_version' => $consentVersion,
        ]);

        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    /**
     * Helper: ¿el usuario ya respondió la versión vigente? (cualquier decisión).
     * "later" NO cuenta como respondido — queremos volver a preguntar la próxima sesión.
     */
    public function hasAnsweredCurrentVersion(int $userId, int $empresaId, string $consentVersion): bool
    {
        $decision = $this->findDecision($userId, $empresaId, $consentVersion);
        return $decision !== null && $decision !== self::DECISION_LATER;
    }
}
