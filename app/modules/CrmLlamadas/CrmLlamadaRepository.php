<?php

declare(strict_types=1);

namespace App\Modules\CrmLlamadas;

use App\Core\Database;
use PDO;

class CrmLlamadaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAllWithSearch(int $empresaId, int $limit, int $offset, string $search = '', string $sortColumn = 'created_at', string $sortDir = 'DESC', bool $onlyDeleted = false): array
    {
        $delCond = $onlyDeleted ? 'l.deleted_at IS NOT NULL' : 'l.deleted_at IS NULL';
        
        // Unimos con usuarios para traer el nombre del usuario asociado (si existe)
        $sql = "
            SELECT l.*, u.nombre as usuario_nombre
            FROM crm_llamadas l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            WHERE l.empresa_id = :empresa_id AND $delCond
        ";
        $params = [':empresa_id' => $empresaId];

        if ($search !== '') {
            $sql .= " AND (l.numero_origen LIKE :search1 OR l.destino LIKE :search2 OR l.interno LIKE :search3 OR l.atendio LIKE :search4 OR u.nombre LIKE :search5)";
            $searchStr = '%' . $search . '%';
            $params[':search1'] = $searchStr;
            $params[':search2'] = $searchStr;
            $params[':search3'] = $searchStr;
            $params[':search4'] = $searchStr;
            $params[':search5'] = $searchStr;
        }

        $validColumns = ['id', 'fecha', 'numero_origen', 'destino', 'duracion', 'interno', 'atendio', 'usuario_nombre'];
        $sortColumn = in_array($sortColumn, $validColumns) ? $sortColumn : 'fecha';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        // Fix for alias in order by if needed. Let's map it safely.
        $orderByCol = $sortColumn === 'usuario_nombre' ? 'u.nombre' : 'l.' . $sortColumn;

        $sql .= " ORDER BY {$orderByCol} {$sortDir} LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(int $empresaId, string $search = '', bool $onlyDeleted = false): int
    {
        $delCond = $onlyDeleted ? 'l.deleted_at IS NOT NULL' : 'l.deleted_at IS NULL';
        $sql = "
            SELECT COUNT(*) 
            FROM crm_llamadas l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            WHERE l.empresa_id = :empresa_id AND $delCond
        ";
        $params = [':empresa_id' => $empresaId];

        if ($search !== '') {
            $sql .= " AND (l.numero_origen LIKE :search1 OR l.destino LIKE :search2 OR l.interno LIKE :search3 OR l.atendio LIKE :search4 OR u.nombre LIKE :search5)";
            $searchStr = '%' . $search . '%';
            $params[':search1'] = $searchStr;
            $params[':search2'] = $searchStr;
            $params[':search3'] = $searchStr;
            $params[':search4'] = $searchStr;
            $params[':search5'] = $searchStr;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findByIdAndEmpresa(int $id, int $empresaId, bool $includeDeleted = false): ?CrmLlamada
    {
        $delCond = $includeDeleted ? '1=1' : 'l.deleted_at IS NULL';
        $sql = "
            SELECT l.*, u.nombre as usuario_nombre
            FROM crm_llamadas l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            WHERE l.id = :id AND l.empresa_id = :empresa_id AND $delCond
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        $llamada = new CrmLlamada();
        foreach ($data as $k => $v) {
            if (property_exists($llamada, $k)) {
                $llamada->$k = $v;
            }
        }
        $llamada->id = (int)$llamada->id;
        $llamada->empresa_id = (int)$llamada->empresa_id;
        $llamada->usuario_id = $llamada->usuario_id ? (int)$llamada->usuario_id : null;

        return $llamada;
    }

    public function delete(int $id, int $empresaId): void
    {
        $sql = "UPDATE crm_llamadas SET deleted_at = NOW() WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
    }

    public function restore(int $id, int $empresaId): void
    {
        $sql = "UPDATE crm_llamadas SET deleted_at = NULL WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
    }

    public function forceDelete(int $id, int $empresaId): void
    {
        $sql = "DELETE FROM crm_llamadas WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
    }
}
