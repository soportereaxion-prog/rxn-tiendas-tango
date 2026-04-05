<?php
declare(strict_types=1);

namespace App\Modules\CrmPedidosServicio;

use App\Core\Database;
use PDO;

class PedidoServicioRepository
{
    private const SEARCHABLE_FIELDS = [
        'numero' => 'CAST(ps.numero AS CHAR)',
        'cliente' => 'ps.cliente_nombre',
        'solicito' => 'ps.solicito',
        'articulo' => 'ps.articulo_nombre',
        'clasificacion' => 'ps.clasificacion_codigo',
        'estado' => 'CASE WHEN ps.fecha_finalizado IS NULL THEN "abierto" ELSE "finalizado" END',
        'usuario' => 'ps.usuario_nombre',
    ];

    private const DEFAULT_CLASSIFICATIONS = [
        'ABONADO',
        'INCIDENTE',
        'CONSULTA',
        'CAPACITACION',
        'IMPLEMENTACION',
        'MANTENIMIENTO',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ensureSchema();
    }

    public function previewNextNumero(int $empresaId): int
    {
        $configStmt = $this->db->prepare('SELECT pds_numero_base FROM empresa_config_crm WHERE empresa_id = :empresa_id');
        $configStmt->execute([':empresa_id' => $empresaId]);
        $base = (int) $configStmt->fetchColumn();

        $stmt = $this->db->prepare('SELECT COALESCE(MAX(numero), 0) FROM crm_pedidos_servicio WHERE empresa_id = :empresa_id');
        $stmt->execute([':empresa_id' => $empresaId]);
        $maxDb = (int) $stmt->fetchColumn();

        return max(1, $base + 1, $maxDb + 1);
    }

    public function countAll(int $empresaId, string $search = '', string $field = 'all', string $estado = '', array $advancedFilters = []): int
    {
        $sql = 'SELECT COUNT(*) FROM crm_pedidos_servicio ps WHERE ps.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];

        if ($estado === 'papelera') {
            $sql .= ' AND ps.deleted_at IS NOT NULL';
        } else {
            $sql .= ' AND ps.deleted_at IS NULL';
            $this->applyEstadoFilter($sql, $params, $estado);
        }

        $this->applySearch($sql, $params, $search, $field, true);

        // Truculencia SQL dinámica para los filtros por columna:
        if (!empty($advancedFilters)) {
            list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
                'numero' => 'CAST(ps.numero AS CHAR)',
                'fecha_inicio' => 'ps.fecha_inicio',
                'fecha_finalizado' => 'ps.fecha_finalizado',
                'cliente_nombre' => 'ps.cliente_nombre',
                'solicito' => 'ps.solicito',
                'articulo_nombre' => 'ps.articulo_nombre',
                'clasificacion_codigo' => 'ps.clasificacion_codigo',
                'usuario_nombre' => 'ps.usuario_nombre',
                'estado_codigo' => 'CASE WHEN ps.fecha_finalizado IS NULL THEN "abierto" ELSE "finalizado" END'
            ]);
            if ($advSql !== '') {
                $sql .= $advSql;
                foreach ($advParams as $i => $val) {
                    $pKey = ':adv_c_' . $i;
                    $sql = substr_replace($sql, $pKey, strpos($sql, '?'), 1);
                    $params[$pKey] = $val;
                }
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
        string $orderBy = 'fecha_inicio',
        string $orderDir = 'DESC',
        array $advancedFilters = []
    ): array {
        $offset = max(0, ($page - 1) * $limit);
        $sql = 'SELECT ps.*,
                CASE WHEN ps.fecha_finalizado IS NULL THEN "abierto" ELSE "finalizado" END AS estado_ui
            FROM crm_pedidos_servicio ps
            WHERE ps.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];

        if ($estado === 'papelera') {
            $sql .= ' AND ps.deleted_at IS NOT NULL';
        } else {
            $sql .= ' AND ps.deleted_at IS NULL';
            $this->applyEstadoFilter($sql, $params, $estado);
        }

        $this->applySearch($sql, $params, $search, $field, true);

        // Truculencia SQL dinámica para los filtros por columna:
        if (!empty($advancedFilters)) {
            list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
                'numero' => 'CAST(ps.numero AS CHAR)',
                'fecha_inicio' => 'ps.fecha_inicio',
                'fecha_finalizado' => 'ps.fecha_finalizado',
                'cliente_nombre' => 'ps.cliente_nombre',
                'solicito' => 'ps.solicito',
                'articulo_nombre' => 'ps.articulo_nombre',
                'clasificacion_codigo' => 'ps.clasificacion_codigo',
                'usuario_nombre' => 'ps.usuario_nombre',
                'estado_codigo' => 'CASE WHEN ps.fecha_finalizado IS NULL THEN "abierto" ELSE "finalizado" END'
            ]);
            if ($advSql !== '') {
                $sql .= $advSql;
                foreach ($advParams as $i => $val) {
                    $pKey = ':adv_f_' . $i;
                    $sql = substr_replace($sql, $pKey, strpos($sql, '?'), 1);
                    $params[$pKey] = $val;
                }
            }
        }

        $allowedColumns = ['numero', 'fecha_inicio', 'fecha_finalizado', 'cliente_nombre', 'articulo_nombre', 'clasificacion_codigo', 'duracion_neta_segundos'];
        if (!in_array($orderBy, $allowedColumns, true)) {
            $orderBy = 'fecha_inicio';
        }

        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= ' ORDER BY ps.' . $orderBy . ' ' . $orderDir . ', ps.numero DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findSuggestions(int $empresaId, string $search = '', string $field = 'all', string $estado = '', int $limit = 3): array
    {
        if (trim($search) === '') {
            return [];
        }

        $sql = 'SELECT ps.id, ps.numero, ps.cliente_nombre, ps.solicito, ps.articulo_nombre, ps.clasificacion_codigo, ps.fecha_finalizado
            FROM crm_pedidos_servicio ps
            WHERE ps.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];

        if ($estado === 'papelera') {
            $sql .= ' AND ps.deleted_at IS NOT NULL';
        } else {
            $sql .= ' AND ps.deleted_at IS NULL';
            $this->applyEstadoFilter($sql, $params, $estado);
        }
        
        $this->applySearch($sql, $params, $search, $field, true);
        $sql .= ' ORDER BY ps.fecha_inicio DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, int $empresaId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_pedidos_servicio WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
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

                $stmt = $this->db->prepare('INSERT INTO crm_pedidos_servicio (
                    empresa_id,
                    usuario_id,
                    usuario_nombre,
                    numero,
                    fecha_inicio,
                    fecha_finalizado,
                    cliente_id,
                    cliente_fuente,
                    cliente_nombre,
                    cliente_documento,
                    cliente_email,
                    solicito,
                    nro_pedido,
                    articulo_id,
                    articulo_codigo,
                    articulo_nombre,
                    articulo_precio_unitario,
                    clasificacion_codigo,
                    clasificacion_id_tango,
                    descuento_segundos,
                    diagnostico,
                    motivo_descuento,
                    duracion_bruta_segundos,
                    duracion_neta_segundos,
                    tiempo_decimal,
                    created_at,
                    updated_at
                ) VALUES (
                    :empresa_id,
                    :usuario_id,
                    :usuario_nombre,
                    :numero,
                    :fecha_inicio,
                    :fecha_finalizado,
                    :cliente_id,
                    :cliente_fuente,
                    :cliente_nombre,
                    :cliente_documento,
                    :cliente_email,
                    :solicito,
                    :nro_pedido,
                    :articulo_id,
                    :articulo_codigo,
                    :articulo_nombre,
                    :articulo_precio_unitario,
                    :clasificacion_codigo,
                    :clasificacion_id_tango,
                    :descuento_segundos,
                    :diagnostico,
                    :motivo_descuento,
                    :duracion_bruta_segundos,
                    :duracion_neta_segundos,
                    :tiempo_decimal,
                    NOW(),
                    NOW()
                )');
                $stmt->execute($this->buildPayload($data));

                $id = (int) $this->db->lastInsertId();
                $this->db->commit();

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

        throw new \RuntimeException('No se pudo crear el pedido de servicio.');
    }

    public function update(int $id, int $empresaId, array $data): bool
    {
        $data['empresa_id'] = $empresaId;

        $stmt = $this->db->prepare('UPDATE crm_pedidos_servicio SET
                fecha_inicio = :fecha_inicio,
                fecha_finalizado = :fecha_finalizado,
                cliente_id = :cliente_id,
                cliente_fuente = :cliente_fuente,
                cliente_nombre = :cliente_nombre,
                cliente_documento = :cliente_documento,
                cliente_email = :cliente_email,
                solicito = :solicito,
                nro_pedido = :nro_pedido,
                articulo_id = :articulo_id,
                articulo_codigo = :articulo_codigo,
                articulo_nombre = :articulo_nombre,
                articulo_precio_unitario = :articulo_precio_unitario,
                clasificacion_codigo = :clasificacion_codigo,
                clasificacion_id_tango = :clasificacion_id_tango,
                descuento_segundos = :descuento_segundos,
                diagnostico = :diagnostico,
                motivo_descuento = :motivo_descuento,
                duracion_bruta_segundos = :duracion_bruta_segundos,
                duracion_neta_segundos = :duracion_neta_segundos,
                tiempo_decimal = :tiempo_decimal,
                updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id');

        $payload = $this->buildPayload($data);
        unset($payload[':numero'], $payload[':usuario_id'], $payload[':usuario_nombre']);
        $payload[':id'] = $id;

        return $stmt->execute($payload);
    }

    public function deleteByIds(array $ids, int $empresaId): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'UPDATE crm_pedidos_servicio SET deleted_at = NOW() WHERE empresa_id = ? AND nro_pedido IS NULL AND id IN (' . $placeholders . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$empresaId], array_map('intval', $ids)));

        return $stmt->rowCount();
    }

    public function restoreByIds(array $ids, int $empresaId): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'UPDATE crm_pedidos_servicio SET deleted_at = NULL WHERE empresa_id = ? AND id IN (' . $placeholders . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$empresaId], array_map('intval', $ids)));

        return $stmt->rowCount();
    }

    public function forceDeleteByIds(array $ids, int $empresaId): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'DELETE FROM crm_pedidos_servicio WHERE empresa_id = ? AND id IN (' . $placeholders . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$empresaId], array_map('intval', $ids)));

        return $stmt->rowCount();
    }

    public function copy(int $id, int $empresaId): void
    {
        $pedido = $this->findById($id, $empresaId);
        if (!$pedido) {
            throw new \RuntimeException('El pedido de servicio a copiar no existe o no pertenece a la empresa.');
        }

        $pedido['numero'] = $this->previewNextNumero($empresaId);
        $pedido['fecha_inicio'] = date('Y-m-d H:i:s');
        $pedido['fecha_finalizado'] = null;
        $pedido['usuario_id'] = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $pedido['usuario_nombre'] = $_SESSION['user_name'] ?? 'Usuario';
        $pedido['nro_pedido'] = null;
        $pedido['tango_sync_status'] = null;
        $pedido['tango_sync_error'] = null;
        $pedido['tango_sync_payload'] = null;
        $pedido['tango_sync_response'] = null;

        $this->create($pedido);
    }

    public function findClientSuggestions(int $empresaId, string $term, int $limit = 5): array
    {
        $termRaw = trim($term);
        $termWildcard = '%' . $termRaw . '%';
        
        $sql = 'SELECT id, nombre, apellido, razon_social, email, documento, codigo_tango
            FROM crm_clientes
            WHERE empresa_id = :empresa_id';
            
        if ($termRaw !== '') {
            $sql .= ' AND (
                razon_social LIKE :t1
                OR codigo_tango LIKE :t2
                OR email LIKE :t3
                OR documento LIKE :t4
                OR nombre LIKE :t5
                OR apellido LIKE :t6
                OR CAST(id AS CHAR) LIKE :t7
            )
            ORDER BY
                CASE 
                    WHEN razon_social = :o_exact1 THEN 1
                    WHEN razon_social LIKE :o_start1 THEN 2
                    WHEN razon_social LIKE :o_any1 THEN 3
                    WHEN codigo_tango = :o_exact2 THEN 4
                    WHEN codigo_tango LIKE :o_start2 THEN 5
                    WHEN codigo_tango LIKE :o_any2 THEN 6
                    WHEN documento = :o_exact3 THEN 7
                    ELSE 99 
                END ASC,
                razon_social ASC, nombre ASC, apellido ASC';
        } else {
            $sql .= ' ORDER BY razon_social ASC, nombre ASC, apellido ASC';
        }
            
        $sql .= ' LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        
        if ($termRaw !== '') {
            $stmt->bindValue(':t1', $termWildcard, PDO::PARAM_STR);
            $stmt->bindValue(':t2', $termWildcard, PDO::PARAM_STR);
            $stmt->bindValue(':t3', $termWildcard, PDO::PARAM_STR);
            $stmt->bindValue(':t4', $termWildcard, PDO::PARAM_STR);
            $stmt->bindValue(':t5', $termWildcard, PDO::PARAM_STR);
            $stmt->bindValue(':t6', $termWildcard, PDO::PARAM_STR);
            $stmt->bindValue(':t7', $termWildcard, PDO::PARAM_STR);
            
            $stmt->bindValue(':o_exact1', $termRaw, PDO::PARAM_STR);
            $stmt->bindValue(':o_start1', $termRaw . '%', PDO::PARAM_STR);
            $stmt->bindValue(':o_any1', '%' . $termRaw . '%', PDO::PARAM_STR);
            
            $stmt->bindValue(':o_exact2', $termRaw, PDO::PARAM_STR);
            $stmt->bindValue(':o_start2', $termRaw . '%', PDO::PARAM_STR);
            $stmt->bindValue(':o_any2', '%' . $termRaw . '%', PDO::PARAM_STR);
            
            $stmt->bindValue(':o_exact3', $termRaw, PDO::PARAM_STR);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findClientById(int $empresaId, int $clienteId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, nombre, apellido, razon_social, email, documento,
                codigo_tango, id_gva14_tango, id_gva01_condicion_venta, id_gva10_lista_precios, id_gva23_vendedor, id_gva24_transporte
            FROM crm_clientes
            WHERE empresa_id = :empresa_id AND id = :id
            LIMIT 1');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $clienteId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findArticleSuggestions(int $empresaId, string $term, int $limit = 5): array
    {
        $termRaw = trim($term);
        $termWildcard = '%' . $termRaw . '%';
        
        $sql = 'SELECT id, codigo_externo, nombre, descripcion
            FROM crm_articulos
            WHERE empresa_id = :empresa_id';
            
        if ($termRaw !== '') {
            $sql .= ' AND (
                nombre LIKE :t1
                OR codigo_externo LIKE :t2
                OR descripcion LIKE :t3
                OR CAST(id AS CHAR) LIKE :t4
            )
            ORDER BY
                CASE 
                    WHEN codigo_externo = :o_exact1 THEN 1
                    WHEN codigo_externo LIKE :o_start1 THEN 2
                    WHEN codigo_externo LIKE :o_any1 THEN 3
                    WHEN descripcion = :o_exact2 THEN 4
                    WHEN descripcion LIKE :o_start2 THEN 5
                    WHEN descripcion LIKE :o_any2 THEN 6
                    WHEN nombre = :o_exact3 THEN 7
                    WHEN nombre LIKE :o_start3 THEN 8
                    ELSE 99
                END ASC,
                codigo_externo ASC, nombre ASC';
        } else {
            $sql .= ' ORDER BY nombre ASC, codigo_externo ASC';
        }
            
        $sql .= ' LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        
        if ($termRaw !== '') {
            $stmt->bindValue(':t1', $termWildcard, PDO::PARAM_STR);
            $stmt->bindValue(':t2', $termWildcard, PDO::PARAM_STR);
            $stmt->bindValue(':t3', $termWildcard, PDO::PARAM_STR);
            $stmt->bindValue(':t4', $termWildcard, PDO::PARAM_STR);

            $stmt->bindValue(':o_exact1', $termRaw, PDO::PARAM_STR);
            $stmt->bindValue(':o_start1', $termRaw . '%', PDO::PARAM_STR);
            $stmt->bindValue(':o_any1', '%' . $termRaw . '%', PDO::PARAM_STR);
            
            $stmt->bindValue(':o_exact2', $termRaw, PDO::PARAM_STR);
            $stmt->bindValue(':o_start2', $termRaw . '%', PDO::PARAM_STR);
            $stmt->bindValue(':o_any2', '%' . $termRaw . '%', PDO::PARAM_STR);

            $stmt->bindValue(':o_exact3', $termRaw, PDO::PARAM_STR);
            $stmt->bindValue(':o_start3', $termRaw . '%', PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findArticleById(int $empresaId, int $articuloId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, codigo_externo, nombre, descripcion, precio, precio_lista_1, precio_lista_2
            FROM crm_articulos
            WHERE empresa_id = :empresa_id AND id = :id
            LIMIT 1');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $articuloId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findClasificacionSuggestions(int $empresaId, string $term, int $limit = 5): array
    {
        $term = trim($term);
        $items = [];

        foreach (self::DEFAULT_CLASSIFICATIONS as $code) {
            $items[$code] = $code;
        }

        $stmt = $this->db->prepare('SELECT DISTINCT clasificacion_codigo
            FROM crm_pedidos_servicio
            WHERE empresa_id = :empresa_id AND clasificacion_codigo IS NOT NULL AND clasificacion_codigo <> "" AND deleted_at IS NULL
            ORDER BY clasificacion_codigo ASC');
        $stmt->execute([':empresa_id' => $empresaId]);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $code) {
            $normalized = strtoupper(trim((string) $code));
            if ($normalized !== '') {
                $items[$normalized] = $normalized;
            }
        }

        $filtered = array_values(array_filter(array_keys($items), static function (string $code) use ($term): bool {
            if ($term === '') {
                return true;
            }

            return str_contains($code, strtoupper($term));
        }));

        return array_slice($filtered, 0, $limit);
    }

    private function applyEstadoFilter(string &$sql, array &$params, string $estado): void
    {
        if ($estado === 'abierto') {
            $sql .= ' AND ps.fecha_finalizado IS NULL';
            return;
        }

        if ($estado === 'finalizado') {
            $sql .= ' AND ps.fecha_finalizado IS NOT NULL';
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
        return [
            ':empresa_id' => (int) $data['empresa_id'],
            ':usuario_id' => $data['usuario_id'] ?? null,
            ':usuario_nombre' => $data['usuario_nombre'] ?? null,
            ':numero' => (int) ($data['numero'] ?? 0),
            ':fecha_inicio' => $data['fecha_inicio'],
            ':fecha_finalizado' => $data['fecha_finalizado'],
            ':cliente_id' => $data['cliente_id'],
            ':cliente_fuente' => $data['cliente_fuente'],
            ':cliente_nombre' => $data['cliente_nombre'],
            ':cliente_documento' => $data['cliente_documento'],
            ':cliente_email' => $data['cliente_email'],
            ':solicito' => $data['solicito'],
            ':nro_pedido' => $data['nro_pedido'],
            ':articulo_id' => $data['articulo_id'],
            ':articulo_codigo' => $data['articulo_codigo'],
            ':articulo_nombre' => $data['articulo_nombre'],
            ':articulo_precio_unitario' => (float) ($data['articulo_precio_unitario'] ?? 0),
            ':clasificacion_codigo' => $data['clasificacion_codigo'],
            ':clasificacion_id_tango' => $data['clasificacion_id_tango'] ?? null,
            ':descuento_segundos' => (int) $data['descuento_segundos'],
            ':diagnostico' => $data['diagnostico'],
            ':motivo_descuento' => $data['motivo_descuento'],
            ':duracion_bruta_segundos' => $data['duracion_bruta_segundos'],
            ':duracion_neta_segundos' => $data['duracion_neta_segundos'],
            ':tiempo_decimal' => (float) ($data['tiempo_decimal'] ?? 0),
        ];
    }

    public function getAdjuntos(int $pedidoId): array
    {
        $stmt = $this->db->prepare('SELECT id, name as file_name, path as file_path, label, created_at FROM crm_pedidos_servicio_adjuntos WHERE pedido_servicio_id = :pedido_id ORDER BY id ASC');
        $stmt->execute([':pedido_id' => $pedidoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function syncAdjuntos(int $pedidoId, int $empresaId, array $base64Adjuntos): void
    {
        if (empty($base64Adjuntos)) {
            return;
        }

        $baseDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/pds-diagnostico/' . date('Y/m');
        if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
            return;
        }

        foreach ($base64Adjuntos as $rawItem) {
            $item = json_decode((string) $rawItem, true);
            if (!is_array($item) || empty($item['data']) || empty($item['extension'])) {
                continue;
            }

            $imgParts = explode(';base64,', $item['data']);
            if (count($imgParts) !== 2) {
                continue;
            }

            $extension = strtolower(preg_replace('/[^a-z0-9]/', '', $item['extension']));
            $fileData = base64_decode($imgParts[1]);
            if ($fileData === false) {
                continue;
            }

            $fileName = sprintf('pds_%d_%s.%s', $pedidoId, uniqid('', true), $extension);
            $filePath = $baseDir . '/' . $fileName;
            
            if (file_put_contents($filePath, $fileData) !== false) {
                $publicUrl = '/uploads/pds-diagnostico/' . date('Y/m') . '/' . $fileName;
                
                $stmt = $this->db->prepare('INSERT INTO crm_pedidos_servicio_adjuntos (pedido_servicio_id, empresa_id, name, path, label) VALUES (:pedido_id, :empresa_id, :file_name, :file_path, :label)');
                $stmt->execute([
                    ':pedido_id' => $pedidoId,
                    ':empresa_id' => $empresaId,
                    ':file_name' => $fileName,
                    ':file_path' => $publicUrl,
                    ':label' => $item['label'] ?? '#imagen'
                ]);
            }
        }
    }

    public function markAsSentToTango(int $id, int $empresaId, string $pedidoNumero, string $payload, string $response): void
    {
        $stmt = $this->db->prepare('UPDATE crm_pedidos_servicio SET
            tango_sync_status = "success",
            tango_sync_error = NULL,
            tango_sync_payload = :payload,
            tango_sync_response = :response,
            nro_pedido = :nro_pedido
            WHERE id = :id AND empresa_id = :empresa_id');
        
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':nro_pedido' => $pedidoNumero,
            ':payload' => $payload,
            ':response' => $response,
        ]);
    }

    public function markAsErrorToTango(int $id, int $empresaId, string $payload, string $errorText, string $response = ''): void
    {
        $stmt = $this->db->prepare('UPDATE crm_pedidos_servicio SET
            tango_sync_status = "error",
            tango_sync_error = :error_text,
            tango_sync_payload = :payload,
            tango_sync_response = :response
            WHERE id = :id AND empresa_id = :empresa_id');
        
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':error_text' => $errorText,
            ':payload' => $payload,
            ':response' => $response === '' ? null : $response,
        ]);
    }

    private function ensureSchema(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS crm_pedidos_servicio (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            numero INT NOT NULL,
            fecha_inicio DATETIME NOT NULL,
            fecha_finalizado DATETIME NULL,
            cliente_id INT NULL,
            cliente_fuente VARCHAR(50) NULL,
            cliente_nombre VARCHAR(180) NOT NULL,
            cliente_documento VARCHAR(50) NULL,
            cliente_email VARCHAR(150) NULL,
            solicito VARCHAR(150) NOT NULL,
            nro_pedido VARCHAR(80) NULL,
            articulo_id INT NOT NULL,
            articulo_codigo VARCHAR(60) NULL,
            articulo_nombre VARCHAR(255) NOT NULL,
            clasificacion_codigo VARCHAR(80) NULL,
            clasificacion_id_tango INT NULL,
            descuento_segundos INT UNSIGNED NOT NULL DEFAULT 0,
            diagnostico TEXT NULL,
            motivo_descuento TEXT NULL,
            duracion_bruta_segundos INT UNSIGNED NULL,
            duracion_neta_segundos INT UNSIGNED NULL,
            tango_sync_status VARCHAR(50) NULL,
            tango_sync_error TEXT NULL,
            tango_sync_payload JSON NULL,
            tango_sync_response JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_crm_pedidos_servicio_empresa_numero (empresa_id, numero),
            KEY idx_crm_pedidos_servicio_empresa_fecha (empresa_id, fecha_inicio),
            KEY idx_crm_pedidos_servicio_cliente (empresa_id, cliente_id),
            KEY idx_crm_pedidos_servicio_articulo (empresa_id, articulo_id),
            KEY idx_crm_pedidos_servicio_clasificacion (empresa_id, clasificacion_codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        try {
            $this->db->exec('ALTER TABLE crm_pedidos_servicio CHANGE falla motivo_descuento TEXT NULL');
        } catch (\Throwable $e) {}
        
        try {
            $this->db->exec('ALTER TABLE crm_pedidos_servicio ADD COLUMN tango_sync_status VARCHAR(50) NULL AFTER duracion_neta_segundos, ADD COLUMN tango_sync_error TEXT NULL AFTER tango_sync_status, ADD COLUMN tango_sync_payload JSON NULL AFTER tango_sync_error, ADD COLUMN tango_sync_response JSON NULL AFTER tango_sync_payload');
        } catch (\Throwable $e) {}

        try {
            $this->db->exec('ALTER TABLE crm_pedidos_servicio ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL');
        } catch (\Throwable $e) {}

        try {
            $this->db->exec('ALTER TABLE crm_pedidos_servicio ADD COLUMN clasificacion_id_tango INT NULL AFTER clasificacion_codigo');
        } catch (\Throwable $e) {}

        try {
            $this->db->exec('ALTER TABLE crm_pedidos_servicio ADD COLUMN usuario_id INT NULL AFTER empresa_id, ADD COLUMN usuario_nombre VARCHAR(180) NULL AFTER usuario_id');
        } catch (\Throwable $e) {}

        try {
            // Backfill temporal para setear el usuario 1 a los PDS historicos sin asignar
            $this->db->exec("UPDATE crm_pedidos_servicio SET usuario_id = 1, usuario_nombre = 'Sergio Majeras' WHERE usuario_id IS NULL");
        } catch (\Throwable $e) {}

        try {
            $this->db->exec('CREATE TABLE IF NOT EXISTS crm_pedidos_servicio_adjuntos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_servicio_id INT NOT NULL,
                empresa_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                path VARCHAR(500) NOT NULL,
                label VARCHAR(50) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_adjuntos_pedido_id (pedido_servicio_id),
                FOREIGN KEY (pedido_servicio_id) REFERENCES crm_pedidos_servicio(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } catch (\Throwable $e) {}
    }
}
