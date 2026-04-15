<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos;

use App\Core\Database;
use PDO;

/**
 * Acceso a la tabla `crm_mail_templates` (plantillas HTML para envíos masivos).
 * Todas las operaciones están scopeadas por empresa_id y respetan soft-delete.
 */
class TemplateRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllByEmpresa(int $empresaId, string $search = ''): array
    {
        $sql = "SELECT t.id, t.nombre, t.descripcion, t.report_id, t.asunto,
                       t.created_by, t.created_at, t.updated_at,
                       r.nombre AS report_nombre
                FROM crm_mail_templates t
                LEFT JOIN crm_mail_reports r
                       ON r.id = t.report_id AND r.deleted_at IS NULL
                WHERE t.empresa_id = :empresa_id AND t.deleted_at IS NULL";

        $params = [':empresa_id' => $empresaId];

        if ($search !== '') {
            $sql .= " AND (t.nombre LIKE :search OR t.descripcion LIKE :search OR t.asunto LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY t.updated_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByIdForEmpresa(int $id, int $empresaId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM crm_mail_templates
             WHERE id = :id AND empresa_id = :empresa_id AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO crm_mail_templates
                (empresa_id, nombre, descripcion, report_id, asunto, body_html, available_vars_json, created_by)
             VALUES
                (:empresa_id, :nombre, :descripcion, :report_id, :asunto, :body_html, :available_vars_json, :created_by)"
        );
        $stmt->execute([
            ':empresa_id' => $data['empresa_id'],
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':report_id' => $data['report_id'] ?? null,
            ':asunto' => $data['asunto'],
            ':body_html' => $data['body_html'],
            ':available_vars_json' => $data['available_vars_json'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $empresaId, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE crm_mail_templates
             SET nombre = :nombre,
                 descripcion = :descripcion,
                 report_id = :report_id,
                 asunto = :asunto,
                 body_html = :body_html,
                 available_vars_json = :available_vars_json
             WHERE id = :id AND empresa_id = :empresa_id AND deleted_at IS NULL"
        );
        $ok = $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':report_id' => $data['report_id'] ?? null,
            ':asunto' => $data['asunto'],
            ':body_html' => $data['body_html'],
            ':available_vars_json' => $data['available_vars_json'] ?? null,
        ]);
        return $ok && $stmt->rowCount() > 0;
    }

    public function softDelete(int $id, int $empresaId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE crm_mail_templates
             SET deleted_at = NOW()
             WHERE id = :id AND empresa_id = :empresa_id AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        return $stmt->rowCount() > 0;
    }

    public function nameExists(int $empresaId, string $nombre, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM crm_mail_templates
                WHERE empresa_id = :empresa_id AND nombre = :nombre AND deleted_at IS NULL";
        $params = [':empresa_id' => $empresaId, ':nombre' => $nombre];
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Lista liviana de reportes disponibles para asociar a una plantilla.
     * @return list<array{id: int, nombre: string, root_entity: string}>
     */
    public function listAvailableReports(int $empresaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nombre, root_entity
             FROM crm_mail_reports
             WHERE empresa_id = :empresa_id AND deleted_at IS NULL
             ORDER BY nombre ASC"
        );
        $stmt->execute([':empresa_id' => $empresaId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn($r) => [
            'id' => (int) $r['id'],
            'nombre' => (string) $r['nombre'],
            'root_entity' => (string) $r['root_entity'],
        ], $rows);
    }

    public function findReportByIdForEmpresa(int $reportId, int $empresaId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nombre, root_entity, config_json
             FROM crm_mail_reports
             WHERE id = :id AND empresa_id = :empresa_id AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([':id' => $reportId, ':empresa_id' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
