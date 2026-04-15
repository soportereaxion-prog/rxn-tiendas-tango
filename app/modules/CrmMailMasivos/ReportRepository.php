<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos;

use App\Core\Database;
use PDO;

/**
 * Acceso a la tabla `crm_mail_reports` (reportes guardados del diseñador Links).
 * Todas las operaciones están scopeadas por empresa_id y soft-delete.
 */
class ReportRepository
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
        $sql = "SELECT id, nombre, descripcion, root_entity, created_by, created_at, updated_at
                FROM crm_mail_reports
                WHERE empresa_id = :empresa_id AND deleted_at IS NULL";

        $params = [':empresa_id' => $empresaId];

        if ($search !== '') {
            $sql .= " AND (nombre LIKE :search OR descripcion LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY updated_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByIdForEmpresa(int $id, int $empresaId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM crm_mail_reports
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
            "INSERT INTO crm_mail_reports
                (empresa_id, nombre, descripcion, root_entity, config_json, created_by)
             VALUES
                (:empresa_id, :nombre, :descripcion, :root_entity, :config_json, :created_by)"
        );
        $stmt->execute([
            ':empresa_id' => $data['empresa_id'],
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':root_entity' => $data['root_entity'],
            ':config_json' => $data['config_json'],
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
            "UPDATE crm_mail_reports
             SET nombre = :nombre,
                 descripcion = :descripcion,
                 root_entity = :root_entity,
                 config_json = :config_json
             WHERE id = :id AND empresa_id = :empresa_id AND deleted_at IS NULL"
        );
        $ok = $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':root_entity' => $data['root_entity'],
            ':config_json' => $data['config_json'],
        ]);
        return $ok && $stmt->rowCount() > 0;
    }

    public function softDelete(int $id, int $empresaId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE crm_mail_reports
             SET deleted_at = NOW()
             WHERE id = :id AND empresa_id = :empresa_id AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        return $stmt->rowCount() > 0;
    }

    public function nameExists(int $empresaId, string $nombre, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM crm_mail_reports
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
}
