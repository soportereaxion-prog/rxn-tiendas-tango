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

    public function findAllWithSearch(int $empresaId, int $limit, int $offset, string $search = '', string $sortColumn = 'created_at', string $sortDir = 'DESC', bool $onlyDeleted = false, array $advancedFilters = []): array
    {
        $delCond = $onlyDeleted ? 'l.deleted_at IS NOT NULL' : 'l.deleted_at IS NULL';

        // Unimos con usuarios y crm_clientes para traer el nombre respectivo.
        // Subselect a crm_pedidos_servicio para detectar si la llamada generó
        // un PDS — devuelve el ID y el número del PDS más reciente (orden DESC
        // por si se duplicó el vínculo accidentalmente). LEFT JOIN sería más
        // limpio pero rompe el agrupamiento por id de llamada cuando hay 1+
        // PDS vinculados.
        $sql = "
            SELECT l.*, u.nombre as usuario_nombre,
                   IFNULL(NULLIF(cc.razon_social, ''), CONCAT(cc.nombre, ' ', cc.apellido)) as cliente_nombre,
                   (SELECT p.id FROM crm_pedidos_servicio p
                     WHERE p.llamada_id = l.id AND p.deleted_at IS NULL
                     ORDER BY p.id DESC LIMIT 1) AS pds_id,
                   (SELECT p2.numero FROM crm_pedidos_servicio p2
                     WHERE p2.llamada_id = l.id AND p2.deleted_at IS NULL
                     ORDER BY p2.id DESC LIMIT 1) AS pds_numero
            FROM crm_llamadas l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            LEFT JOIN crm_clientes cc ON l.cliente_id = cc.id
            WHERE l.empresa_id = :empresa_id AND $delCond
        ";
        $params = [':empresa_id' => $empresaId];

        // Filtros avanzados
        $grabacionExpr = "CASE WHEN l.mp3 IS NOT NULL AND l.mp3 != '' THEN 'Con audio' WHEN l.evento_link IS NOT NULL AND l.evento_link != '' THEN l.evento_link ELSE 'Sin audio' END";
        // Estado del vínculo con PDS — expresado como string para que el
        // AdvancedQueryFilter pueda matchear con 'Con PDS' / 'Sin PDS'.
        $pdsEstadoExpr = "CASE WHEN EXISTS (SELECT 1 FROM crm_pedidos_servicio p3 WHERE p3.llamada_id = l.id AND p3.deleted_at IS NULL) THEN 'Con PDS' ELSE 'Sin PDS' END";
        $filterMap = [
            'fecha' => 'l.fecha',
            'numero_origen' => 'l.numero_origen',
            'destino' => 'l.destino',
            'interno' => 'l.interno',
            'usuario_nombre' => 'u.nombre',
            'cliente_nombre' => 'IFNULL(NULLIF(cc.razon_social, \'\'), CONCAT(cc.nombre, \' \', cc.apellido))',
            'grabacion_estado' => $grabacionExpr,
            'pds_estado' => $pdsEstadoExpr
        ];

        [$advFilterSql, $advParams] = \App\Core\AdvancedQueryFilter::build($advancedFilters, $filterMap);
        if ($advFilterSql !== '') {
            $sql .= " AND (" . $advFilterSql . ")";
            $params = array_merge($params, $advParams);
        }

        if ($search !== '') {
            $sql .= " AND (l.numero_origen LIKE :search1 OR l.destino LIKE :search2 OR l.interno LIKE :search3 OR l.atendio LIKE :search4 OR u.nombre LIKE :search5)";
            $searchStr = '%' . $search . '%';
            $params[':search1'] = $searchStr;
            $params[':search2'] = $searchStr;
            $params[':search3'] = $searchStr;
            $params[':search4'] = $searchStr;
            $params[':search5'] = $searchStr;
        }

        $validColumns = ['id', 'fecha', 'numero_origen', 'destino', 'duracion', 'interno', 'atendio', 'usuario_nombre', 'cliente_nombre'];
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

    public function countAll(int $empresaId, string $search = '', bool $onlyDeleted = false, array $advancedFilters = []): int
    {
        $delCond = $onlyDeleted ? 'l.deleted_at IS NOT NULL' : 'l.deleted_at IS NULL';
        $sql = "
            SELECT COUNT(*) 
            FROM crm_llamadas l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            LEFT JOIN crm_clientes cc ON l.cliente_id = cc.id
            WHERE l.empresa_id = :empresa_id AND $delCond
        ";
        $params = [':empresa_id' => $empresaId];

        $grabacionExpr = "CASE WHEN l.mp3 IS NOT NULL AND l.mp3 != '' THEN 'Con audio' WHEN l.evento_link IS NOT NULL AND l.evento_link != '' THEN l.evento_link ELSE 'Sin audio' END";
        $pdsEstadoExpr = "CASE WHEN EXISTS (SELECT 1 FROM crm_pedidos_servicio p3 WHERE p3.llamada_id = l.id AND p3.deleted_at IS NULL) THEN 'Con PDS' ELSE 'Sin PDS' END";
        $filterMap = [
            'fecha' => 'l.fecha',
            'numero_origen' => 'l.numero_origen',
            'destino' => 'l.destino',
            'interno' => 'l.interno',
            'usuario_nombre' => 'u.nombre',
            'cliente_nombre' => 'IFNULL(NULLIF(cc.razon_social, \'\'), CONCAT(cc.nombre, \' \', cc.apellido))',
            'grabacion_estado' => $grabacionExpr,
            'pds_estado' => $pdsEstadoExpr
        ];

        [$advFilterSql, $advParams] = \App\Core\AdvancedQueryFilter::build($advancedFilters, $filterMap);
        if ($advFilterSql !== '') {
            $sql .= " AND (" . $advFilterSql . ")";
            $params = array_merge($params, $advParams);
        }

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
            SELECT l.*, u.nombre as usuario_nombre,
                   IFNULL(NULLIF(cc.razon_social, ''), CONCAT(cc.nombre, ' ', cc.apellido)) as cliente_nombre
            FROM crm_llamadas l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            LEFT JOIN crm_clientes cc ON l.cliente_id = cc.id
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

    public function vincularClienteLlamada(int $llamadaId, int $empresaId, int $clienteId, string $numeroOrigen): void
    {
        try {
            $this->db->beginTransaction();

            if ($numeroOrigen !== '') {
                // Actualizar todas las llamadas de este origen
                $sqlUpdateMulti = "UPDATE crm_llamadas SET cliente_id = :cliente_id WHERE (numero_origen = :numero_origen OR origen = :origen_alt) AND empresa_id = :empresa_id";
                $stmtMulti = $this->db->prepare($sqlUpdateMulti);
                $stmtMulti->execute([
                    ':cliente_id' => $clienteId,
                    ':numero_origen' => $numeroOrigen,
                    ':origen_alt' => $numeroOrigen,
                    ':empresa_id' => $empresaId
                ]);

                $sqlUpsert = "
                    INSERT INTO crm_telefonos_clientes (empresa_id, numero_origen, cliente_id, created_at, updated_at) 
                    VALUES (:empresa_id, :numero_origen, :cliente_id, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE cliente_id = VALUES(cliente_id), updated_at = NOW()
                ";
                $stmtUpsert = $this->db->prepare($sqlUpsert);
                $stmtUpsert->execute([
                    ':empresa_id' => $empresaId,
                    ':numero_origen' => $numeroOrigen,
                    ':cliente_id' => $clienteId
                ]);
            } else {
                // Solo por ID si no hay origen
                $sqlUpdate = "UPDATE crm_llamadas SET cliente_id = :cliente_id WHERE id = :id AND empresa_id = :empresa_id";
                $stmt = $this->db->prepare($sqlUpdate);
                $stmt->execute([
                    ':cliente_id' => $clienteId,
                    ':id' => $llamadaId,
                    ':empresa_id' => $empresaId
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function desvincularClienteLlamada(int $llamadaId, int $empresaId, string $numeroOrigen): void
    {
        try {
            $this->db->beginTransaction();

            if ($numeroOrigen !== '') {
                $sqlDel = "DELETE FROM crm_telefonos_clientes WHERE numero_origen = :numero_origen AND empresa_id = :empresa_id";
                $stmtDel = $this->db->prepare($sqlDel);
                $stmtDel->execute([
                    ':numero_origen' => $numeroOrigen,
                    ':empresa_id' => $empresaId
                ]);

                $sqlUpdate = "UPDATE crm_llamadas SET cliente_id = NULL WHERE (numero_origen = :numero_origen OR origen = :origen_alt) AND empresa_id = :empresa_id";
                $stmtUpd = $this->db->prepare($sqlUpdate);
                $stmtUpd->execute([
                    ':numero_origen' => $numeroOrigen,
                    ':origen_alt' => $numeroOrigen,
                    ':empresa_id' => $empresaId
                ]);
            } else {
                $sqlUpdate = "UPDATE crm_llamadas SET cliente_id = NULL WHERE id = :id AND empresa_id = :empresa_id";
                $stmtUpd = $this->db->prepare($sqlUpdate);
                $stmtUpd->execute([
                    ':id' => $llamadaId,
                    ':empresa_id' => $empresaId
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
