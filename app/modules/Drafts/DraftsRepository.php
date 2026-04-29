<?php

declare(strict_types=1);

namespace App\Modules\Drafts;

use App\Core\Database;
use PDO;

class DraftsRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function find(int $userId, int $empresaId, string $modulo, string $refKey): ?array
    {
        $sql = "SELECT id, user_id, empresa_id, modulo, ref_key, payload_json, created_at, updated_at
                FROM drafts
                WHERE user_id = :uid AND empresa_id = :eid AND modulo = :modulo AND ref_key = :ref
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':uid' => $userId,
            ':eid' => $empresaId,
            ':modulo' => $modulo,
            ':ref' => $refKey,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function upsert(int $userId, int $empresaId, string $modulo, string $refKey, string $payloadJson): void
    {
        $sql = "INSERT INTO drafts (user_id, empresa_id, modulo, ref_key, payload_json, created_at, updated_at)
                VALUES (:uid, :eid, :modulo, :ref, :payload, NOW(), NOW())
                ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), updated_at = NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':uid' => $userId,
            ':eid' => $empresaId,
            ':modulo' => $modulo,
            ':ref' => $refKey,
            ':payload' => $payloadJson,
        ]);
    }

    /**
     * Devuelve todos los borradores de un usuario en una empresa, ordenados por
     * último update DESC. Usado por el panel "Mis borradores" en Mi Perfil.
     *
     * NO devuelve el payload_json (puede ser de hasta 1MB cada uno y el panel
     * solo necesita metadata para listar). Si el panel quiere ofrecer
     * preview, que pegue al endpoint /api/internal/drafts/get individualmente.
     */
    public function findAllByUser(int $userId, int $empresaId): array
    {
        $sql = "SELECT id, modulo, ref_key, created_at, updated_at,
                       OCTET_LENGTH(payload_json) AS payload_bytes
                FROM drafts
                WHERE user_id = :uid AND empresa_id = :eid
                ORDER BY updated_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':uid' => $userId, ':eid' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function delete(int $userId, int $empresaId, string $modulo, string $refKey): void
    {
        $sql = "DELETE FROM drafts WHERE user_id = :uid AND empresa_id = :eid AND modulo = :modulo AND ref_key = :ref";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':uid' => $userId,
            ':eid' => $empresaId,
            ':modulo' => $modulo,
            ':ref' => $refKey,
        ]);
    }
}
