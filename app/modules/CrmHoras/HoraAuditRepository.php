<?php

declare(strict_types=1);

namespace App\Modules\CrmHoras;

use App\Core\Database;
use PDO;

/**
 * HoraAuditRepository — registro de mutaciones administrativas sobre crm_horas.
 *
 * Solo se inserta cuando el cambio lo hace alguien distinto al dueño del turno.
 * El listado es VISIBLE solo para super admin (vista /admin/horas/audit).
 */
class HoraAuditRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    public function record(int $empresaId, int $horaId, int $ownerUserId, string $accion, ?array $before, ?array $after, ?string $motivo, int $performedBy): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO crm_horas_audit
                (empresa_id, hora_id, owner_user_id, accion, before_json, after_json, motivo, performed_by)
            VALUES
                (:e, :h, :o, :a, :bf, :af, :m, :pb)
        ');
        $stmt->execute([
            ':e'  => $empresaId,
            ':h'  => $horaId,
            ':o'  => $ownerUserId,
            ':a'  => $accion,
            ':bf' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            ':af' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            ':m'  => $motivo,
            ':pb' => $performedBy,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function paginate(int $empresaId, int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare("
            SELECT a.*, u_owner.nombre AS owner_nombre, u_perf.nombre AS performed_by_nombre
            FROM crm_horas_audit a
            LEFT JOIN usuarios u_owner ON u_owner.id = a.owner_user_id
            LEFT JOIN usuarios u_perf  ON u_perf.id  = a.performed_by
            WHERE a.empresa_id = :e
            ORDER BY a.performed_at DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute([':e' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countAll(int $empresaId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM crm_horas_audit WHERE empresa_id = :e');
        $stmt->execute([':e' => $empresaId]);
        return (int) $stmt->fetchColumn();
    }
}
