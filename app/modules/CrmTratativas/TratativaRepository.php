<?php
declare(strict_types=1);

namespace App\Modules\CrmTratativas;

use App\Core\Database;
use PDO;

class TratativaRepository
{
    public const ESTADOS = ['nueva', 'en_curso', 'ganada', 'perdida', 'pausada'];

    private const SEARCHABLE_FIELDS = [
        'numero' => 'CAST(t.numero AS CHAR)',
        'titulo' => 't.titulo',
        'cliente' => 't.cliente_nombre',
        'estado' => 't.estado',
        'usuario' => 't.usuario_nombre',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function previewNextNumero(int $empresaId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(numero), 0) FROM crm_tratativas WHERE empresa_id = :empresa_id');
        $stmt->execute([':empresa_id' => $empresaId]);
        $maxDb = (int) $stmt->fetchColumn();

        return max(1, $maxDb + 1);
    }

    public function countAll(int $empresaId, string $search = '', string $field = 'all', string $estado = '', array $advancedFilters = []): int
    {
        $sql = 'SELECT COUNT(*) FROM crm_tratativas t WHERE t.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];

        if ($estado === 'papelera') {
            $sql .= ' AND t.deleted_at IS NOT NULL';
        } else {
            $sql .= ' AND t.deleted_at IS NULL';
            $this->applyEstadoFilter($sql, $params, $estado);
        }

        $this->applySearch($sql, $params, $search, $field, true);

        if (!empty($advancedFilters)) {
            list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
                'numero' => 'CAST(t.numero AS CHAR)',
                'titulo' => 't.titulo',
                'cliente_nombre' => 't.cliente_nombre',
                'estado' => 't.estado',
                'probabilidad' => 'CAST(t.probabilidad AS CHAR)',
                'valor_estimado' => 'CAST(t.valor_estimado AS CHAR)',
                'fecha_apertura' => 't.fecha_apertura',
                'fecha_cierre_estimado' => 't.fecha_cierre_estimado',
                'usuario_nombre' => 't.usuario_nombre',
            ]);
            if ($advSql !== '') {
                $sql .= ' AND (' . $advSql . ')';
                $params = array_merge($params, $advParams);
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findAllPaginated(
        int $empresaId,
        int $page = 1,
        int $limit = 25,
        string $search = '',
        string $field = 'all',
        string $estado = '',
        string $orderBy = 'created_at',
        string $orderDir = 'DESC',
        array $advancedFilters = []
    ): array {
        $offset = max(0, ($page - 1) * $limit);
        $sql = 'SELECT t.*,
                (SELECT COUNT(*) FROM crm_pedidos_servicio pds WHERE pds.tratativa_id = t.id AND pds.empresa_id = t.empresa_id AND pds.deleted_at IS NULL) AS pds_count,
                (SELECT COUNT(*) FROM crm_presupuestos prs WHERE prs.tratativa_id = t.id AND prs.empresa_id = t.empresa_id AND prs.deleted_at IS NULL) AS presupuestos_count
            FROM crm_tratativas t
            WHERE t.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];

        if ($estado === 'papelera') {
            $sql .= ' AND t.deleted_at IS NOT NULL';
        } else {
            $sql .= ' AND t.deleted_at IS NULL';
            $this->applyEstadoFilter($sql, $params, $estado);
        }

        $this->applySearch($sql, $params, $search, $field, true);

        if (!empty($advancedFilters)) {
            list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
                'numero' => 'CAST(t.numero AS CHAR)',
                'titulo' => 't.titulo',
                'cliente_nombre' => 't.cliente_nombre',
                'estado' => 't.estado',
                'probabilidad' => 'CAST(t.probabilidad AS CHAR)',
                'valor_estimado' => 'CAST(t.valor_estimado AS CHAR)',
                'fecha_apertura' => 't.fecha_apertura',
                'fecha_cierre_estimado' => 't.fecha_cierre_estimado',
                'usuario_nombre' => 't.usuario_nombre',
            ]);
            if ($advSql !== '') {
                $sql .= ' AND (' . $advSql . ')';
                $params = array_merge($params, $advParams);
            }
        }

        $allowedColumns = ['numero', 'titulo', 'cliente_nombre', 'estado', 'probabilidad', 'valor_estimado', 'fecha_apertura', 'fecha_cierre_estimado', 'created_at'];
        if (!in_array($orderBy, $allowedColumns, true)) {
            $orderBy = 'created_at';
        }

        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= ' ORDER BY t.' . $orderBy . ' ' . $orderDir . ', t.numero DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, int $empresaId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_tratativas WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findSuggestions(int $empresaId, string $search = '', string $field = 'all', string $estado = '', int $limit = 3): array
    {
        if (trim($search) === '') {
            return [];
        }

        $sql = 'SELECT t.id, t.numero, t.titulo, t.cliente_nombre, t.estado, t.probabilidad
            FROM crm_tratativas t
            WHERE t.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];

        if ($estado === 'papelera') {
            $sql .= ' AND t.deleted_at IS NOT NULL';
        } else {
            $sql .= ' AND t.deleted_at IS NULL';
            $this->applyEstadoFilter($sql, $params, $estado);
        }

        $this->applySearch($sql, $params, $search, $field, true);
        $sql .= ' ORDER BY t.created_at DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): int
    {
        $attempts = 0;

        while ($attempts < 3) {
            $attempts++;

            try {
                $this->db->beginTransaction();
                $numero = $this->previewNextNumero((int) $data['empresa_id']);
                $data['numero'] = $numero;

                $stmt = $this->db->prepare('INSERT INTO crm_tratativas (
                        empresa_id,
                        numero,
                        usuario_id,
                        usuario_nombre,
                        cliente_id,
                        cliente_nombre,
                        titulo,
                        descripcion,
                        estado,
                        probabilidad,
                        valor_estimado,
                        fecha_apertura,
                        fecha_cierre_estimado,
                        fecha_cierre_real,
                        motivo_cierre,
                        created_at,
                        updated_at
                    ) VALUES (
                        :empresa_id,
                        :numero,
                        :usuario_id,
                        :usuario_nombre,
                        :cliente_id,
                        :cliente_nombre,
                        :titulo,
                        :descripcion,
                        :estado,
                        :probabilidad,
                        :valor_estimado,
                        :fecha_apertura,
                        :fecha_cierre_estimado,
                        :fecha_cierre_real,
                        :motivo_cierre,
                        NOW(),
                        NOW()
                    )');
                $stmt->execute($this->buildPayload($data));

                $id = (int) $this->db->lastInsertId();
                $this->db->commit();

                // Hook explícito: proyectar la tratativa como evento en la agenda
                // (solo si tiene fecha_cierre_estimado, el proyector ya lo valida).
                try {
                    $row = $data;
                    $row['id'] = $id;
                    $row['numero'] = $numero;
                    (new \App\Modules\CrmAgenda\AgendaProyectorService())->onTratativaSaved($row);
                } catch (\Throwable) {}

                return $id;
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                if ($attempts >= 3) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('No se pudo crear la tratativa.');
    }

    public function update(int $id, int $empresaId, array $data): bool
    {
        $data['empresa_id'] = $empresaId;

        $stmt = $this->db->prepare('UPDATE crm_tratativas SET
                cliente_id = :cliente_id,
                cliente_nombre = :cliente_nombre,
                titulo = :titulo,
                descripcion = :descripcion,
                estado = :estado,
                probabilidad = :probabilidad,
                valor_estimado = :valor_estimado,
                fecha_apertura = :fecha_apertura,
                fecha_cierre_estimado = :fecha_cierre_estimado,
                fecha_cierre_real = :fecha_cierre_real,
                motivo_cierre = :motivo_cierre,
                updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id');

        $payload = $this->buildPayload($data);
        unset($payload[':numero'], $payload[':usuario_id'], $payload[':usuario_nombre']);
        $payload[':id'] = $id;

        $ok = $stmt->execute($payload);

        // Hook explícito: re-proyectar la tratativa en la agenda.
        try {
            $row = $data;
            $row['id'] = $id;
            (new \App\Modules\CrmAgenda\AgendaProyectorService())->onTratativaSaved($row);
        } catch (\Throwable) {}

        return $ok;
    }

    public function deleteByIds(array $ids, int $empresaId): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'UPDATE crm_tratativas SET deleted_at = NOW() WHERE empresa_id = ? AND id IN (' . $placeholders . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$empresaId], array_map('intval', $ids)));

        // Hook explícito: eliminar los eventos proyectados de estas tratativas en la agenda.
        try {
            $proyector = new \App\Modules\CrmAgenda\AgendaProyectorService();
            foreach ($ids as $trId) {
                $proyector->onTratativaDeleted((int) $trId, $empresaId);
            }
        } catch (\Throwable) {}

        return $stmt->rowCount();
    }

    public function restoreByIds(array $ids, int $empresaId): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'UPDATE crm_tratativas SET deleted_at = NULL WHERE empresa_id = ? AND id IN (' . $placeholders . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$empresaId], array_map('intval', $ids)));

        return $stmt->rowCount();
    }

    public function forceDeleteByIds(array $ids, int $empresaId): int
    {
        if ($ids === []) {
            return 0;
        }

        // Antes de borrar definitivamente, desvinculamos PDS y Presupuestos
        // para no dejar FKs flotando. tratativa_id queda en NULL (soft-link).
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $intIds = array_map('intval', $ids);

        $unlinkPds = $this->db->prepare('UPDATE crm_pedidos_servicio SET tratativa_id = NULL WHERE empresa_id = ? AND tratativa_id IN (' . $placeholders . ')');
        $unlinkPds->execute(array_merge([$empresaId], $intIds));

        $unlinkPres = $this->db->prepare('UPDATE crm_presupuestos SET tratativa_id = NULL WHERE empresa_id = ? AND tratativa_id IN (' . $placeholders . ')');
        $unlinkPres->execute(array_merge([$empresaId], $intIds));

        $unlinkNotas = $this->db->prepare('UPDATE crm_notas SET tratativa_id = NULL WHERE empresa_id = ? AND tratativa_id IN (' . $placeholders . ')');
        $unlinkNotas->execute(array_merge([$empresaId], $intIds));

        $sql = 'DELETE FROM crm_tratativas WHERE empresa_id = ? AND id IN (' . $placeholders . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$empresaId], $intIds));

        return $stmt->rowCount();
    }

    /**
     * Retorna los PDS asociados a una tratativa (activos).
     */
    public function findPdsByTratativaId(int $tratativaId, int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT id, numero, fecha_inicio, fecha_finalizado, cliente_nombre, articulo_nombre, solicito, clasificacion_codigo, usuario_nombre, nro_pedido, tango_sync_status
            FROM crm_pedidos_servicio
            WHERE tratativa_id = :tratativa_id
              AND empresa_id = :empresa_id
              AND deleted_at IS NULL
            ORDER BY fecha_inicio DESC, numero DESC');
        $stmt->execute([
            ':tratativa_id' => $tratativaId,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Retorna los Presupuestos asociados a una tratativa (activos).
     */
    public function findPresupuestosByTratativaId(int $tratativaId, int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT id, numero, fecha, cliente_nombre_snapshot, total, estado, usuario_nombre, tango_sync_status, nro_comprobante_tango
            FROM crm_presupuestos
            WHERE tratativa_id = :tratativa_id
              AND empresa_id = :empresa_id
              AND deleted_at IS NULL
            ORDER BY fecha DESC, numero DESC');
        $stmt->execute([
            ':tratativa_id' => $tratativaId,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Retorna las Notas asociadas a una tratativa (activas).
     * Delega en CrmNotaRepository para no duplicar la query.
     */
    public function findNotasByTratativaId(int $tratativaId, int $empresaId): array
    {
        $notasRepo = new \App\Modules\CrmNotas\CrmNotaRepository();
        return $notasRepo->findByTratativaId($tratativaId, $empresaId);
    }

    /**
     * Valida que una tratativa exista, esté activa y pertenezca a la empresa.
     * Usado por los controllers de PDS y Presupuestos al recibir ?tratativa_id=.
     */
    public function existsActiveForEmpresa(int $tratativaId, int $empresaId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM crm_tratativas
            WHERE id = :id AND empresa_id = :empresa_id AND deleted_at IS NULL
            LIMIT 1');
        $stmt->execute([
            ':id' => $tratativaId,
            ':empresa_id' => $empresaId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function applyEstadoFilter(string &$sql, array &$params, string $estado): void
    {
        if ($estado === '' || $estado === 'papelera') {
            return;
        }

        if (in_array($estado, self::ESTADOS, true)) {
            $sql .= ' AND t.estado = :estado';
            $params[':estado'] = $estado;
        }
    }

    private function applySearch(string &$sql, array &$params, string $search, string $field, bool $hasWhere = false): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $operator = $hasWhere ? ' AND ' : ' WHERE ';

        if ($field !== 'all' && isset(self::SEARCHABLE_FIELDS[$field])) {
            $sql .= $operator . self::SEARCHABLE_FIELDS[$field] . ' LIKE :search';
            $params[':search'] = '%' . $search . '%';
            return;
        }

        $conditions = [];
        foreach (self::SEARCHABLE_FIELDS as $key => $column) {
            $placeholder = ':search_' . $key;
            $conditions[] = $column . ' LIKE ' . $placeholder;
            $params[$placeholder] = '%' . $search . '%';
        }

        $sql .= $operator . '(' . implode(' OR ', $conditions) . ')';
    }

    private function buildPayload(array $data): array
    {
        $estado = (string) ($data['estado'] ?? 'nueva');
        if (!in_array($estado, self::ESTADOS, true)) {
            $estado = 'nueva';
        }

        $probabilidad = (int) ($data['probabilidad'] ?? 0);
        if ($probabilidad < 0) {
            $probabilidad = 0;
        } elseif ($probabilidad > 100) {
            $probabilidad = 100;
        }

        return [
            ':empresa_id' => (int) $data['empresa_id'],
            ':numero' => (int) ($data['numero'] ?? 0),
            ':usuario_id' => $data['usuario_id'] ?? null,
            ':usuario_nombre' => $data['usuario_nombre'] ?? null,
            ':cliente_id' => !empty($data['cliente_id']) ? (int) $data['cliente_id'] : null,
            ':cliente_nombre' => $data['cliente_nombre'] ?? null,
            ':titulo' => (string) $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':estado' => $estado,
            ':probabilidad' => $probabilidad,
            ':valor_estimado' => (float) ($data['valor_estimado'] ?? 0),
            ':fecha_apertura' => !empty($data['fecha_apertura']) ? $data['fecha_apertura'] : null,
            ':fecha_cierre_estimado' => !empty($data['fecha_cierre_estimado']) ? $data['fecha_cierre_estimado'] : null,
            ':fecha_cierre_real' => !empty($data['fecha_cierre_real']) ? $data['fecha_cierre_real'] : null,
            ':motivo_cierre' => $data['motivo_cierre'] ?? null,
        ];
    }
}
