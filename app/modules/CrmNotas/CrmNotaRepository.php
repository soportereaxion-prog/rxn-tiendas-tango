<?php

declare(strict_types=1);

namespace App\Modules\CrmNotas;

use App\Core\Database;
use PDO;
use RuntimeException;

class CrmNotaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS crm_notas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT NOT NULL,
                cliente_id INT DEFAULT NULL,
                titulo VARCHAR(255) NOT NULL,
                contenido TEXT NOT NULL,
                tags VARCHAR(500) DEFAULT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_notas_empresa (empresa_id),
                INDEX idx_notas_cliente (cliente_id),
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
                FOREIGN KEY (cliente_id) REFERENCES crm_clientes(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $this->db->exec($sql);

        $sqlTags = "
            CREATE TABLE IF NOT EXISTS crm_notas_tags_diccionario (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT NOT NULL,
                nombre VARCHAR(100) NOT NULL,
                UNIQUE KEY uk_empresa_tag (empresa_id, nombre),
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $this->db->exec($sqlTags);
    }

    public function findAllWithClientName(int $empresaId, int $limit, int $offset, string $search = '', string $sortColumn = 'created_at', string $sortDir = 'DESC', bool $onlyDeleted = false): array
    {
        $delCond = $onlyDeleted ? 'n.deleted_at IS NOT NULL' : 'n.deleted_at IS NULL';
        $sql = "
            SELECT n.*, c.razon_social as cliente_nombre, c.codigo_tango as cliente_codigo
            FROM crm_notas n
            LEFT JOIN crm_clientes c ON n.cliente_id = c.id
            WHERE n.empresa_id = :empresa_id AND $delCond
        ";
        $params = [':empresa_id' => $empresaId];

        if ($search !== '') {
            $sql .= " AND (n.titulo LIKE :search1 OR n.contenido LIKE :search2 OR c.razon_social LIKE :search3)";
            $searchTerm = '%' . $search . '%';
            $params[':search1'] = $searchTerm;
            $params[':search2'] = $searchTerm;
            $params[':search3'] = $searchTerm;
        }

        $validColumns = ['id', 'created_at', 'titulo', 'cliente_nombre'];
        $sortColumn = in_array($sortColumn, $validColumns) ? $sortColumn : 'created_at';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql .= " ORDER BY {$sortColumn} {$sortDir} LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        
        // PDO bind limits as ints carefully
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
        $delCond = $onlyDeleted ? 'n.deleted_at IS NOT NULL' : 'n.deleted_at IS NULL';
        $sql = "
            SELECT COUNT(*) 
            FROM crm_notas n
            LEFT JOIN crm_clientes c ON n.cliente_id = c.id
            WHERE n.empresa_id = :empresa_id AND $delCond
        ";
        $params = [':empresa_id' => $empresaId];

        if ($search !== '') {
            $sql .= " AND (n.titulo LIKE :search1 OR n.contenido LIKE :search2 OR c.razon_social LIKE :search3)";
            $searchTerm = '%' . $search . '%';
            $params[':search1'] = $searchTerm;
            $params[':search2'] = $searchTerm;
            $params[':search3'] = $searchTerm;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findByIdAndEmpresa(int $id, int $empresaId, bool $includeDeleted = false): ?CrmNota
    {
        $delCond = $includeDeleted ? '1=1' : 'n.deleted_at IS NULL';
        $sql = "
            SELECT n.*, c.razon_social as cliente_nombre, c.codigo_tango as cliente_codigo
            FROM crm_notas n
            LEFT JOIN crm_clientes c ON n.cliente_id = c.id
            WHERE n.id = :id AND n.empresa_id = :empresa_id AND $delCond
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        $nota = new CrmNota();
        foreach ($data as $k => $v) {
            if (property_exists($nota, $k)) {
                $nota->$k = $v;
            }
        }
        // Force types correctly
        $nota->id = (int)$nota->id;
        $nota->empresa_id = (int)$nota->empresa_id;
        $nota->cliente_id = $nota->cliente_id ? (int)$nota->cliente_id : null;
        $nota->activo = (int)$nota->activo;

        return $nota;
    }

    public function save(CrmNota $nota): void
    {
        if (isset($nota->id) && $nota->id > 0) {
            $sql = "UPDATE crm_notas 
                    SET cliente_id = :cliente_id, 
                        titulo = :titulo, 
                        contenido = :contenido, 
                        tags = :tags, 
                        activo = :activo 
                    WHERE id = :id AND empresa_id = :empresa_id";
            $params = [
                ':cliente_id' => $nota->cliente_id,
                ':titulo' => $nota->titulo,
                ':contenido' => $nota->contenido,
                ':tags' => $nota->tags,
                ':activo' => $nota->activo,
                ':id' => $nota->id,
                ':empresa_id' => $nota->empresa_id,
            ];
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO crm_notas (empresa_id, cliente_id, titulo, contenido, tags, activo)
                    VALUES (:empresa_id, :cliente_id, :titulo, :contenido, :tags, :activo)";
            $params = [
                ':empresa_id' => $nota->empresa_id,
                ':cliente_id' => $nota->cliente_id,
                ':titulo' => $nota->titulo,
                ':contenido' => $nota->contenido,
                ':tags' => $nota->tags,
                ':activo' => $nota->activo,
            ];
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $nota->id = (int) $this->db->lastInsertId();
        }

        $this->syncTags((string)$nota->tags, $nota->empresa_id);
    }

    private function syncTags(string $tagsRaw, int $empresaId): void
    {
        if (trim($tagsRaw) === '') {
            return;
        }

        $tagsArray = array_map('trim', explode(',', $tagsRaw));
        $tagsArray = array_filter($tagsArray, fn($t) => $t !== '');

        if (empty($tagsArray)) {
            return;
        }

        $sql = "INSERT IGNORE INTO crm_notas_tags_diccionario (empresa_id, nombre) VALUES (:empresa_id, :nombre)";
        $stmt = $this->db->prepare($sql);
        foreach (array_unique($tagsArray) as $tag) {
            $stmt->execute([':empresa_id' => $empresaId, ':nombre' => mb_strtolower($tag, 'UTF-8')]);
        }
    }

    public function delete(int $id, int $empresaId): void
    {
        $sql = "UPDATE crm_notas SET deleted_at = NOW() WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
    }

    public function restore(int $id, int $empresaId): void
    {
        $sql = "UPDATE crm_notas SET deleted_at = NULL WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
    }

    public function forceDelete(int $id, int $empresaId): void
    {
        $sql = "DELETE FROM crm_notas WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
    }
}
