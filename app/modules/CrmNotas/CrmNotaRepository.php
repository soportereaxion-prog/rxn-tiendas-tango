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
                tratativa_id INT DEFAULT NULL,
                titulo VARCHAR(255) NOT NULL,
                contenido TEXT NOT NULL,
                tags VARCHAR(500) DEFAULT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_notas_empresa (empresa_id),
                INDEX idx_notas_cliente (cliente_id),
                INDEX idx_crm_notas_tratativa (empresa_id, tratativa_id),
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

    public function findAllWithClientName(int $empresaId, int $limit, int $offset, string $search = '', string $sortColumn = 'created_at', string $sortDir = 'DESC', bool $onlyDeleted = false, array $advancedFilters = [], ?int $tratativaId = null): array
    {
        $delCond = $onlyDeleted ? 'n.deleted_at IS NOT NULL' : 'n.deleted_at IS NULL';

        $sql = "
            SELECT n.*,
                   c.razon_social AS cliente_nombre,
                   c.codigo_tango AS cliente_codigo,
                   t.numero AS tratativa_numero,
                   t.titulo AS tratativa_titulo
            FROM crm_notas n
            LEFT JOIN crm_clientes c ON n.cliente_id = c.id
            LEFT JOIN crm_tratativas t ON n.tratativa_id = t.id AND t.empresa_id = n.empresa_id
            WHERE n.empresa_id = :empresa_id AND $delCond
        ";
        $params = [':empresa_id' => $empresaId];

        if ($tratativaId !== null && $tratativaId > 0) {
            $sql .= ' AND n.tratativa_id = :tratativa_id';
            $params[':tratativa_id'] = $tratativaId;
        }

        // Advanced Filters integration
        $filterMap = [
            'id' => 'n.id',
            'titulo' => 'n.titulo',
            'cliente_nombre' => 'c.razon_social',
            'tratativa_numero' => 'CAST(t.numero AS CHAR)',
            'tratativa_titulo' => 't.titulo',
            'tags' => 'n.tags',
            'created_at' => 'n.created_at',
            'fecha_recordatorio' => 'n.fecha_recordatorio',
            'cliente_codigo' => 'c.codigo_tango'
        ];

        [$advFilterSql, $advParams] = \App\Core\AdvancedQueryFilter::build($advancedFilters, $filterMap);
        if ($advFilterSql !== '') {
            $sql .= " AND (" . $advFilterSql . ")";
            $params = array_merge($params, $advParams);
        }

        if ($search !== '') {
            $sql .= " AND (n.titulo LIKE :search1 OR n.contenido LIKE :search2 OR c.razon_social LIKE :search3 OR t.titulo LIKE :search4 OR CAST(t.numero AS CHAR) LIKE :search5)";
            $searchTerm = '%' . $search . '%';
            $params[':search1'] = $searchTerm;
            $params[':search2'] = $searchTerm;
            $params[':search3'] = $searchTerm;
            $params[':search4'] = $searchTerm;
            $params[':search5'] = $searchTerm;
        }

        $validColumns = ['id', 'created_at', 'titulo', 'cliente_nombre', 'tratativa_numero'];
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

    public function countAll(int $empresaId, string $search = '', bool $onlyDeleted = false, array $advancedFilters = [], ?int $tratativaId = null): int
    {
        $delCond = $onlyDeleted ? 'n.deleted_at IS NOT NULL' : 'n.deleted_at IS NULL';
        $sql = "
            SELECT COUNT(*)
            FROM crm_notas n
            LEFT JOIN crm_clientes c ON n.cliente_id = c.id
            LEFT JOIN crm_tratativas t ON n.tratativa_id = t.id AND t.empresa_id = n.empresa_id
            WHERE n.empresa_id = :empresa_id AND $delCond
        ";
        $params = [':empresa_id' => $empresaId];

        if ($tratativaId !== null && $tratativaId > 0) {
            $sql .= ' AND n.tratativa_id = :tratativa_id';
            $params[':tratativa_id'] = $tratativaId;
        }

        $filterMap = [
            'id' => 'n.id',
            'titulo' => 'n.titulo',
            'cliente_nombre' => 'c.razon_social',
            'tratativa_numero' => 'CAST(t.numero AS CHAR)',
            'tratativa_titulo' => 't.titulo',
            'tags' => 'n.tags',
            'created_at' => 'n.created_at',
            'fecha_recordatorio' => 'n.fecha_recordatorio',
            'cliente_codigo' => 'c.codigo_tango'
        ];

        [$advFilterSql, $advParams] = \App\Core\AdvancedQueryFilter::build($advancedFilters, $filterMap);
        if ($advFilterSql !== '') {
            $sql .= " AND (" . $advFilterSql . ")";
            $params = array_merge($params, $advParams);
        }

        if ($search !== '') {
            $sql .= " AND (n.titulo LIKE :search1 OR n.contenido LIKE :search2 OR c.razon_social LIKE :search3 OR t.titulo LIKE :search4 OR CAST(t.numero AS CHAR) LIKE :search5)";
            $searchTerm = '%' . $search . '%';
            $params[':search1'] = $searchTerm;
            $params[':search2'] = $searchTerm;
            $params[':search3'] = $searchTerm;
            $params[':search4'] = $searchTerm;
            $params[':search5'] = $searchTerm;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findByIdAndEmpresa(int $id, int $empresaId, bool $includeDeleted = false): ?CrmNota
    {
        $delCond = $includeDeleted ? '1=1' : 'n.deleted_at IS NULL';
        $sql = "
            SELECT n.*,
                   c.razon_social AS cliente_nombre,
                   c.codigo_tango AS cliente_codigo,
                   t.numero AS tratativa_numero,
                   t.titulo AS tratativa_titulo
            FROM crm_notas n
            LEFT JOIN crm_clientes c ON n.cliente_id = c.id
            LEFT JOIN crm_tratativas t ON n.tratativa_id = t.id AND t.empresa_id = n.empresa_id
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
        $nota->tratativa_id = $nota->tratativa_id ? (int)$nota->tratativa_id : null;
        $nota->activo = (int)$nota->activo;

        return $nota;
    }

    /**
     * Notas asociadas a una tratativa (activas), ordenadas por fecha de creación descendente.
     * Mismo patrón que TratativaRepository::findPdsByTratativaId / findPresupuestosByTratativaId.
     */
    public function findByTratativaId(int $tratativaId, int $empresaId): array
    {
        $sql = "SELECT n.id, n.titulo, n.contenido, n.tags, n.activo, n.created_at,
                       c.razon_social AS cliente_nombre
                FROM crm_notas n
                LEFT JOIN crm_clientes c ON n.cliente_id = c.id
                WHERE n.tratativa_id = :tratativa_id
                  AND n.empresa_id = :empresa_id
                  AND n.deleted_at IS NULL
                ORDER BY n.created_at DESC, n.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tratativa_id' => $tratativaId,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function save(CrmNota $nota): void
    {
        // Si cambia fecha_recordatorio, reseteamos recordatorio_disparado_at
        // para que el late firer lo vuelva a disparar con la nueva fecha.
        $resetDisparo = false;
        if (isset($nota->id) && $nota->id > 0) {
            $stmtPrev = $this->db->prepare("SELECT fecha_recordatorio FROM crm_notas WHERE id = :id AND empresa_id = :empresa_id");
            $stmtPrev->execute([':id' => $nota->id, ':empresa_id' => $nota->empresa_id]);
            $prevFecha = $stmtPrev->fetchColumn();
            if ($prevFecha !== false && (string)$prevFecha !== (string)($nota->fecha_recordatorio ?? '')) {
                $resetDisparo = true;
            }
        }

        if (isset($nota->id) && $nota->id > 0) {
            $sql = "UPDATE crm_notas
                    SET cliente_id = :cliente_id,
                        tratativa_id = :tratativa_id,
                        titulo = :titulo,
                        contenido = :contenido,
                        tags = :tags,
                        fecha_recordatorio = :fecha_recordatorio,
                        " . ($resetDisparo ? "recordatorio_disparado_at = NULL," : "") . "
                        activo = :activo
                    WHERE id = :id AND empresa_id = :empresa_id";
            $params = [
                ':cliente_id' => $nota->cliente_id,
                ':tratativa_id' => $nota->tratativa_id,
                ':titulo' => $nota->titulo,
                ':contenido' => $nota->contenido,
                ':tags' => $nota->tags,
                ':fecha_recordatorio' => $nota->fecha_recordatorio,
                ':activo' => $nota->activo,
                ':id' => $nota->id,
                ':empresa_id' => $nota->empresa_id,
            ];
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO crm_notas (empresa_id, cliente_id, tratativa_id, titulo, contenido, tags, fecha_recordatorio, created_by, activo)
                    VALUES (:empresa_id, :cliente_id, :tratativa_id, :titulo, :contenido, :tags, :fecha_recordatorio, :created_by, :activo)";
            $params = [
                ':empresa_id' => $nota->empresa_id,
                ':cliente_id' => $nota->cliente_id,
                ':tratativa_id' => $nota->tratativa_id,
                ':titulo' => $nota->titulo,
                ':contenido' => $nota->contenido,
                ':tags' => $nota->tags,
                ':fecha_recordatorio' => $nota->fecha_recordatorio,
                ':created_by' => $nota->created_by,
                ':activo' => $nota->activo,
            ];
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $nota->id = (int) $this->db->lastInsertId();
        }

        $this->syncTags((string)$nota->tags, $nota->empresa_id);

        // Hook agenda: proyecta la nota como evento (si tiene fecha_recordatorio).
        // Try/catch defensivo — el proyector ya silencia adentro, pero no queremos que
        // un fallo de instanciación rompa el save.
        try {
            (new \App\Modules\CrmAgenda\AgendaProyectorService())->onNotaSaved((array) $this->fetchRowForProjector($nota->id, $nota->empresa_id));
        } catch (\Throwable) {}
    }

    /**
     * Devuelve la fila de la nota como array plano, lista para el proyector.
     * No hidrata $cliente_nombre porque el proyector usa solo campos directos
     * y resuelve el contexto por su cuenta.
     */
    private function fetchRowForProjector(int $id, int $empresaId): array
    {
        $stmt = $this->db->prepare("
            SELECT n.*, u.nombre AS usuario_nombre
            FROM crm_notas n
            LEFT JOIN usuarios u ON u.id = n.created_by
            WHERE n.id = :id AND n.empresa_id = :empresa_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
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

        try {
            (new \App\Modules\CrmAgenda\AgendaProyectorService())->onNotaDeleted($id, $empresaId);
        } catch (\Throwable) {}
    }

    public function restore(int $id, int $empresaId): void
    {
        $sql = "UPDATE crm_notas SET deleted_at = NULL WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);

        try {
            (new \App\Modules\CrmAgenda\AgendaProyectorService())->onNotaSaved((array) $this->fetchRowForProjector($id, $empresaId));
        } catch (\Throwable) {}
    }

    public function forceDelete(int $id, int $empresaId): void
    {
        // Primero los adjuntos físicos (archivos + filas) para no dejar huérfanos.
        // Soft delete NO borra adjuntos (el usuario puede restaurar la nota).
        try {
            (new \App\Core\Services\AttachmentService())->deleteByOwner($empresaId, 'crm_nota', $id);
        } catch (\Throwable) {
            // Si el service falla, seguimos con el delete de la nota igual —
            // los archivos huérfanos son recuperables por GC; la fila huérfana no.
        }

        $sql = "DELETE FROM crm_notas WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
    }
}
