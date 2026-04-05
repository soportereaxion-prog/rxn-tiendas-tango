<?php
declare(strict_types=1);

namespace App\Modules\CrmPresupuestos;

use App\Core\Database;
use PDO;

class PresupuestoRepository
{
    private const SEARCHABLE_FIELDS = [
        'numero' => 'CAST(p.numero AS CHAR)',
        'cliente' => 'p.cliente_nombre_snapshot',
        'estado' => 'p.estado',
        'fecha' => 'DATE_FORMAT(p.fecha, "%Y-%m-%d %H:%i:%s")',
        'usuario' => 'p.usuario_nombre',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ensureSchema();
    }

    public function previewNextNumero(int $empresaId): int
    {
        $configStmt = $this->db->prepare('SELECT presupuesto_numero_base FROM empresa_config_crm WHERE empresa_id = :empresa_id');
        $configStmt->execute([':empresa_id' => $empresaId]);
        $base = (int) $configStmt->fetchColumn();

        $stmt = $this->db->prepare('SELECT COALESCE(MAX(numero), 0) FROM crm_presupuestos WHERE empresa_id = :empresa_id');
        $stmt->execute([':empresa_id' => $empresaId]);
        $maxDb = (int) $stmt->fetchColumn();

        return max(1, $base + 1, $maxDb + 1);
    }

    public function countAll(int $empresaId, string $search = '', string $field = 'all', string $estado = '', array $advancedFilters = []): int
    {
        $sql = 'SELECT COUNT(*) FROM crm_presupuestos p WHERE p.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];
        $this->applyEstadoFilter($sql, $params, $estado);
        $this->applySearch($sql, $params, $search, $field, true);

        list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
            'numero' => 'CAST(p.numero AS CHAR)',
            'fecha' => 'DATE_FORMAT(p.fecha, "%Y-%m-%d %H:%i:%s")',
            'cliente_nombre_snapshot' => 'p.cliente_nombre_snapshot',
            'estado' => 'p.estado',
            'total' => 'CAST(p.total AS CHAR)',
        ]);
        
        if ($advSql !== '') {
            $sql .= ' AND (' . $advSql . ')';
            $params = array_merge($params, $advParams);
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
        string $orderBy = 'fecha',
        string $orderDir = 'DESC',
        array $advancedFilters = []
    ): array {
        $offset = max(0, ($page - 1) * $limit);
        $sql = 'SELECT p.*,
                (SELECT COUNT(*) FROM crm_presupuesto_items i WHERE i.presupuesto_id = p.id) AS items_count
            FROM crm_presupuestos p
            WHERE p.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];
        
        if ($estado === 'papelera') {
            $sql .= ' AND p.deleted_at IS NOT NULL';
        } else {
            $sql .= ' AND p.deleted_at IS NULL';
            $this->applyEstadoFilter($sql, $params, $estado);
        }
        
        $this->applySearch($sql, $params, $search, $field, true);

        list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
            'numero' => 'CAST(p.numero AS CHAR)',
            'fecha' => 'DATE_FORMAT(p.fecha, "%Y-%m-%d %H:%i:%s")',
            'cliente_nombre_snapshot' => 'p.cliente_nombre_snapshot',
            'estado' => 'p.estado',
            'total' => 'CAST(p.total AS CHAR)',
        ]);
        
        if ($advSql !== '') {
            $sql .= ' AND (' . $advSql . ')';
            $params = array_merge($params, $advParams);
        }

        $allowedColumns = ['numero', 'fecha', 'cliente_nombre_snapshot', 'total', 'estado'];
        if (!in_array($orderBy, $allowedColumns, true)) {
            $orderBy = 'fecha';
        }

        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= ' ORDER BY p.' . $orderBy . ' ' . $orderDir . ', p.numero DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findSuggestions(int $empresaId, string $search = '', string $field = 'all', string $estado = '', int $limit = 3): array
    {
        if (trim($search) === '') {
            return [];
        }

        $sql = 'SELECT p.id, p.numero, p.fecha, p.cliente_nombre_snapshot, p.total, p.estado
            FROM crm_presupuestos p
            WHERE p.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];
        $this->applyEstadoFilter($sql, $params, $estado);
        $this->applySearch($sql, $params, $search, $field, true);
        $sql .= ' ORDER BY p.fecha DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteByIds(array $ids, int $empresaId): int
    {
        if (empty($ids)) return 0;
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE crm_presupuestos SET deleted_at = NOW() WHERE id IN ($inQuery) AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        $params = array_merge($ids, [$empresaId]);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function restoreByIds(array $ids, int $empresaId): int
    {
        if (empty($ids)) return 0;
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE crm_presupuestos SET deleted_at = NULL WHERE id IN ($inQuery) AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        $params = array_merge($ids, [$empresaId]);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function forceDeleteByIds(array $ids, int $empresaId): int
    {
        if (empty($ids)) return 0;
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM crm_presupuestos WHERE id IN ($inQuery) AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        $params = array_merge($ids, [$empresaId]);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function findById(int $id, int $empresaId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_presupuestos WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findItemsByPresupuestoId(int $presupuestoId, int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_presupuesto_items WHERE presupuesto_id = :presupuesto_id AND empresa_id = :empresa_id ORDER BY orden ASC, id ASC');
        $stmt->execute([
            ':presupuesto_id' => $presupuestoId,
            ':empresa_id' => $empresaId,
        ]);

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

                $stmt = $this->db->prepare('INSERT INTO crm_presupuestos (
                        empresa_id, numero, fecha, cliente_id, cliente_nombre_snapshot, cliente_documento_snapshot,
                        deposito_codigo, deposito_nombre_snapshot,
                        condicion_codigo, condicion_nombre_snapshot, condicion_id_interno,
                        transporte_codigo, transporte_nombre_snapshot, transporte_id_interno,
                        lista_codigo, lista_nombre_snapshot, lista_id_interno,
                        vendedor_codigo, vendedor_nombre_snapshot, vendedor_id_interno,
                        subtotal, descuento_total, impuestos_total, total, estado, usuario_id, usuario_nombre, created_at, updated_at
                    ) VALUES (
                        :empresa_id, :numero, :fecha, :cliente_id, :cliente_nombre_snapshot, :cliente_documento_snapshot,
                        :deposito_codigo, :deposito_nombre_snapshot,
                        :condicion_codigo, :condicion_nombre_snapshot, :condicion_id_interno,
                        :transporte_codigo, :transporte_nombre_snapshot, :transporte_id_interno,
                        :lista_codigo, :lista_nombre_snapshot, :lista_id_interno,
                        :vendedor_codigo, :vendedor_nombre_snapshot, :vendedor_id_interno,
                        :subtotal, :descuento_total, :impuestos_total, :total, :estado, :usuario_id, :usuario_nombre, NOW(), NOW()
                    )');
                $payload = $this->buildHeaderPayload($data);
                $payload[':numero'] = $numero;
                $stmt->execute($payload);

                $presupuestoId = (int) $this->db->lastInsertId();
                $this->insertItems($presupuestoId, (int) $data['empresa_id'], $data['items'] ?? []);
                $this->db->commit();

                return $presupuestoId;
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                if ($attempts >= 3) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('No se pudo crear el presupuesto CRM.');
    }

    public function update(int $id, int $empresaId, array $data): void
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('UPDATE crm_presupuestos SET
                    fecha = :fecha,
                    cliente_id = :cliente_id,
                    cliente_nombre_snapshot = :cliente_nombre_snapshot,
                    cliente_documento_snapshot = :cliente_documento_snapshot,
                    deposito_codigo = :deposito_codigo,
                    deposito_nombre_snapshot = :deposito_nombre_snapshot,
                    condicion_codigo = :condicion_codigo,
                    condicion_nombre_snapshot = :condicion_nombre_snapshot,
                    condicion_id_interno = :condicion_id_interno,
                    transporte_codigo = :transporte_codigo,
                    transporte_nombre_snapshot = :transporte_nombre_snapshot,
                    transporte_id_interno = :transporte_id_interno,
                    lista_codigo = :lista_codigo,
                    lista_nombre_snapshot = :lista_nombre_snapshot,
                    lista_id_interno = :lista_id_interno,
                    vendedor_codigo = :vendedor_codigo,
                    vendedor_nombre_snapshot = :vendedor_nombre_snapshot,
                    vendedor_id_interno = :vendedor_id_interno,
                    subtotal = :subtotal,
                    descuento_total = :descuento_total,
                    impuestos_total = :impuestos_total,
                    total = :total,
                    estado = :estado,
                    updated_at = NOW()
                WHERE id = :id AND empresa_id = :empresa_id');
            $payload = $this->buildHeaderPayload($data);
            $payload[':id'] = $id;
            $payload[':empresa_id'] = $empresaId;
            $stmt->execute($payload);

            $deleteStmt = $this->db->prepare('DELETE FROM crm_presupuesto_items WHERE presupuesto_id = :presupuesto_id AND empresa_id = :empresa_id');
            $deleteStmt->execute([
                ':presupuesto_id' => $id,
                ':empresa_id' => $empresaId,
            ]);

            $this->insertItems($id, $empresaId, $data['items'] ?? []);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function findArticleContext(int $empresaId, int $articuloId, ?string $listaCodigo): ?array
    {
        $stmt = $this->db->prepare('SELECT a.id, a.codigo_externo, a.nombre, a.descripcion, a.precio, a.precio_lista_1, a.precio_lista_2,
                ap.precio AS precio_catalogo
            FROM crm_articulos a
            LEFT JOIN crm_articulo_precios ap
                ON ap.empresa_id = a.empresa_id
                AND ap.articulo_id = a.id
                AND ap.lista_codigo = :lista_codigo
            WHERE a.empresa_id = :empresa_id AND a.id = :id
            LIMIT 1');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $articuloId,
            ':lista_codigo' => trim((string) $listaCodigo) !== '' ? trim((string) $listaCodigo) : '__sin_lista__',
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $priceOrigin = 'manual';
        $price = null;

        if ($row['precio_catalogo'] !== null) {
            $price = (float) $row['precio_catalogo'];
            $priceOrigin = 'catalogo';
        } elseif ($row['precio'] !== null) {
            $price = (float) $row['precio'];
            $priceOrigin = 'fallback';
        } elseif ($row['precio_lista_1'] !== null) {
            $price = (float) $row['precio_lista_1'];
            $priceOrigin = 'fallback';
        }

        return [
            'id' => (int) $row['id'],
            'codigo_externo' => (string) ($row['codigo_externo'] ?? ''),
            'nombre' => trim((string) ($row['nombre'] ?? '')),
            'descripcion' => trim((string) ($row['descripcion'] ?? '')),
            'precio_unitario' => $price,
            'precio_origen' => $priceOrigin,
        ];
    }

    private function insertItems(int $presupuestoId, int $empresaId, array $items): void
    {
        if ($items === []) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO crm_presupuesto_items (
                presupuesto_id, empresa_id, orden, articulo_id, articulo_codigo, articulo_descripcion_snapshot,
                lista_codigo_aplicada, cantidad, precio_unitario, bonificacion_porcentaje, importe_bruto, importe_neto, precio_origen, created_at, updated_at
            ) VALUES (
                :presupuesto_id, :empresa_id, :orden, :articulo_id, :articulo_codigo, :articulo_descripcion_snapshot,
                :lista_codigo_aplicada, :cantidad, :precio_unitario, :bonificacion_porcentaje, :importe_bruto, :importe_neto, :precio_origen, NOW(), NOW()
            )');

        foreach ($items as $index => $item) {
            $stmt->execute([
                ':presupuesto_id' => $presupuestoId,
                ':empresa_id' => $empresaId,
                ':orden' => (int) ($item['orden'] ?? ($index + 1)),
                ':articulo_id' => $this->nullableInt($item['articulo_id'] ?? null),
                ':articulo_codigo' => (string) ($item['articulo_codigo'] ?? ''),
                ':articulo_descripcion_snapshot' => (string) ($item['articulo_descripcion'] ?? ''),
                ':lista_codigo_aplicada' => $this->nullableString($item['lista_codigo_aplicada'] ?? null),
                ':cantidad' => (float) ($item['cantidad'] ?? 0),
                ':precio_unitario' => (float) ($item['precio_unitario'] ?? 0),
                ':bonificacion_porcentaje' => (float) ($item['bonificacion_porcentaje'] ?? 0),
                ':importe_bruto' => (float) ($item['importe_bruto'] ?? 0),
                ':importe_neto' => (float) ($item['importe_neto'] ?? 0),
                ':precio_origen' => (string) ($item['precio_origen'] ?? 'manual'),
            ]);
        }
    }

    private function buildHeaderPayload(array $data): array
    {
        return [
            ':empresa_id' => (int) ($data['empresa_id'] ?? 0),
            ':fecha' => (string) ($data['fecha'] ?? ''),
            ':cliente_id' => (int) ($data['cliente_id'] ?? 0),
            ':cliente_nombre_snapshot' => (string) ($data['cliente_nombre_snapshot'] ?? ''),
            ':cliente_documento_snapshot' => $this->nullableString($data['cliente_documento_snapshot'] ?? null),
            ':deposito_codigo' => $this->nullableString($data['deposito_codigo'] ?? null),
            ':deposito_nombre_snapshot' => $this->nullableString($data['deposito_nombre_snapshot'] ?? null),
            ':condicion_codigo' => $this->nullableString($data['condicion_codigo'] ?? null),
            ':condicion_nombre_snapshot' => $this->nullableString($data['condicion_nombre_snapshot'] ?? null),
            ':condicion_id_interno' => $this->nullableInt($data['condicion_id_interno'] ?? null),
            ':transporte_codigo' => $this->nullableString($data['transporte_codigo'] ?? null),
            ':transporte_nombre_snapshot' => $this->nullableString($data['transporte_nombre_snapshot'] ?? null),
            ':transporte_id_interno' => $this->nullableInt($data['transporte_id_interno'] ?? null),
            ':lista_codigo' => $this->nullableString($data['lista_codigo'] ?? null),
            ':lista_nombre_snapshot' => $this->nullableString($data['lista_nombre_snapshot'] ?? null),
            ':lista_id_interno' => $this->nullableInt($data['lista_id_interno'] ?? null),
            ':vendedor_codigo' => $this->nullableString($data['vendedor_codigo'] ?? null),
            ':vendedor_nombre_snapshot' => $this->nullableString($data['vendedor_nombre_snapshot'] ?? null),
            ':vendedor_id_interno' => $this->nullableInt($data['vendedor_id_interno'] ?? null),
            ':subtotal' => (float) ($data['subtotal'] ?? 0),
            ':descuento_total' => (float) ($data['descuento_total'] ?? 0),
            ':impuestos_total' => (float) ($data['impuestos_total'] ?? 0),
            ':total' => (float) ($data['total'] ?? 0),
            ':estado' => (string) ($data['estado'] ?? 'borrador'),
            ':usuario_id' => $data['usuario_id'] ?? null,
            ':usuario_nombre' => $data['usuario_nombre'] ?? null,
        ];
    }

    private function applyEstadoFilter(string &$sql, array &$params, string $estado): void
    {
        $estado = trim($estado);
        if ($estado === '') {
            return;
        }

        $sql .= ' AND p.estado = :estado';
        $params[':estado'] = $estado;
    }

    private function applySearch(string &$sql, array &$params, string $search = '', string $field = 'all', bool $hasWhere = false): void
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

    private function ensureSchema(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS crm_presupuestos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            numero INT NOT NULL,
            fecha DATETIME NOT NULL,
            cliente_id INT NOT NULL,
            cliente_nombre_snapshot VARCHAR(255) NOT NULL,
            cliente_documento_snapshot VARCHAR(50) NULL,
            deposito_codigo VARCHAR(50) NULL,
            deposito_nombre_snapshot VARCHAR(255) NULL,
            condicion_codigo VARCHAR(50) NULL,
            condicion_nombre_snapshot VARCHAR(255) NULL,
            condicion_id_interno INT NULL,
            transporte_codigo VARCHAR(50) NULL,
            transporte_nombre_snapshot VARCHAR(255) NULL,
            transporte_id_interno INT NULL,
            lista_codigo VARCHAR(50) NULL,
            lista_nombre_snapshot VARCHAR(255) NULL,
            lista_id_interno INT NULL,
            vendedor_codigo VARCHAR(50) NULL,
            vendedor_nombre_snapshot VARCHAR(255) NULL,
            vendedor_id_interno INT NULL,
            subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
            descuento_total DECIMAL(15,2) NOT NULL DEFAULT 0,
            impuestos_total DECIMAL(15,2) NOT NULL DEFAULT 0,
            total DECIMAL(15,2) NOT NULL DEFAULT 0,
            estado VARCHAR(30) NOT NULL DEFAULT "borrador",
            usuario_id INT NULL,
            usuario_nombre VARCHAR(180) NULL,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_crm_presupuestos_empresa_numero (empresa_id, numero),
            KEY idx_crm_presupuestos_empresa_fecha (empresa_id, fecha),
            KEY idx_crm_presupuestos_empresa_cliente (empresa_id, cliente_id),
            KEY idx_crm_presupuestos_empresa_estado (empresa_id, estado),
            KEY idx_crm_presupuestos_usuario (empresa_id, usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        try {
            $this->db->exec('ALTER TABLE crm_presupuestos ADD COLUMN usuario_id INT NULL AFTER estado, ADD COLUMN usuario_nombre VARCHAR(180) NULL AFTER usuario_id');
        } catch (\Throwable $e) {}

        try {
            // Backfill temporal para setear el usuario 1 a los Presupuestos historicos sin asignar
            $this->db->exec("UPDATE crm_presupuestos SET usuario_id = 1, usuario_nombre = 'Sergio Majeras' WHERE usuario_id IS NULL");
        } catch (\Throwable $e) {}

        $this->db->exec('CREATE TABLE IF NOT EXISTS crm_presupuesto_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            presupuesto_id INT NOT NULL,
            empresa_id INT NOT NULL,
            orden INT NOT NULL DEFAULT 1,
            articulo_id INT NULL,
            articulo_codigo VARCHAR(100) NOT NULL,
            articulo_descripcion_snapshot VARCHAR(255) NOT NULL,
            lista_codigo_aplicada VARCHAR(50) NULL,
            cantidad DECIMAL(15,4) NOT NULL DEFAULT 1,
            precio_unitario DECIMAL(15,4) NOT NULL DEFAULT 0,
            bonificacion_porcentaje DECIMAL(7,4) NOT NULL DEFAULT 0,
            importe_bruto DECIMAL(15,2) NOT NULL DEFAULT 0,
            importe_neto DECIMAL(15,2) NOT NULL DEFAULT 0,
            precio_origen VARCHAR(20) NOT NULL DEFAULT "manual",
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_crm_presupuesto_items_presupuesto_orden (presupuesto_id, orden),
            KEY idx_crm_presupuesto_items_empresa_articulo (empresa_id, articulo_id),
            CONSTRAINT fk_crm_presupuesto_items_presupuesto FOREIGN KEY (presupuesto_id) REFERENCES crm_presupuestos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->db->exec('CREATE TABLE IF NOT EXISTS crm_articulo_precios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            articulo_id INT NOT NULL,
            lista_codigo VARCHAR(50) NOT NULL,
            precio DECIMAL(15,4) NOT NULL,
            moneda_codigo VARCHAR(10) NULL,
            fecha_ultima_sync DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_crm_articulo_precios_empresa_articulo_lista (empresa_id, articulo_id, lista_codigo),
            KEY idx_crm_articulo_precios_empresa_lista (empresa_id, lista_codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
