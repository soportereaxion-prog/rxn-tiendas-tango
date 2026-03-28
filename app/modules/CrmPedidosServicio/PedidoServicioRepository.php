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
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(numero), 0) + 1 FROM crm_pedidos_servicio WHERE empresa_id = :empresa_id');
        $stmt->execute([':empresa_id' => $empresaId]);

        return max(1, (int) $stmt->fetchColumn());
    }

    public function countAll(int $empresaId, string $search = '', string $field = 'all', string $estado = ''): int
    {
        $sql = 'SELECT COUNT(*) FROM crm_pedidos_servicio ps WHERE ps.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];

        $this->applyEstadoFilter($sql, $params, $estado);
        $this->applySearch($sql, $params, $search, $field, true);

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
        string $orderDir = 'DESC'
    ): array {
        $offset = max(0, ($page - 1) * $limit);
        $sql = 'SELECT ps.*,
                CASE WHEN ps.fecha_finalizado IS NULL THEN "abierto" ELSE "finalizado" END AS estado_ui
            FROM crm_pedidos_servicio ps
            WHERE ps.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];

        $this->applyEstadoFilter($sql, $params, $estado);
        $this->applySearch($sql, $params, $search, $field, true);

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

        $this->applyEstadoFilter($sql, $params, $estado);
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
                    descuento_segundos,
                    diagnostico,
                    falla,
                    duracion_bruta_segundos,
                    duracion_neta_segundos,
                    tiempo_decimal,
                    created_at,
                    updated_at
                ) VALUES (
                    :empresa_id,
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
                    :descuento_segundos,
                    :diagnostico,
                    :falla,
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
                descuento_segundos = :descuento_segundos,
                diagnostico = :diagnostico,
                falla = :falla,
                duracion_bruta_segundos = :duracion_bruta_segundos,
                duracion_neta_segundos = :duracion_neta_segundos,
                tiempo_decimal = :tiempo_decimal,
                updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id');

        $payload = $this->buildPayload($data);
        $payload[':id'] = $id;

        return $stmt->execute($payload);
    }

    public function findClientSuggestions(int $empresaId, string $term, int $limit = 5): array
    {
        if (trim($term) === '') {
            return [];
        }

        $sql = 'SELECT id, nombre, apellido, razon_social, email, documento
            FROM crm_clientes
            WHERE empresa_id = :empresa_id
              AND (
                nombre LIKE :term
                OR apellido LIKE :term
                OR razon_social LIKE :term
                OR email LIKE :term
                OR documento LIKE :term
                OR CAST(id AS CHAR) LIKE :term
            )
            ORDER BY razon_social ASC, nombre ASC, apellido ASC
            LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':term', '%' . trim($term) . '%', PDO::PARAM_STR);
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
        if (trim($term) === '') {
            return [];
        }

        $sql = 'SELECT id, codigo_externo, nombre, descripcion
            FROM articulos
            WHERE empresa_id = :empresa_id
              AND (
                codigo_externo LIKE :term
                OR nombre LIKE :term
                OR descripcion LIKE :term
                OR CAST(id AS CHAR) LIKE :term
            )
            ORDER BY nombre ASC
            LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':term', '%' . trim($term) . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findArticleById(int $empresaId, int $articuloId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, codigo_externo, nombre, descripcion, precio, precio_lista_1, precio_lista_2
            FROM articulos
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
            WHERE empresa_id = :empresa_id AND clasificacion_codigo IS NOT NULL AND clasificacion_codigo <> ""
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
            ':clasificacion_codigo' => $data['clasificacion_codigo'],
            ':descuento_segundos' => (int) $data['descuento_segundos'],
            ':diagnostico' => $data['diagnostico'],
            ':falla' => $data['falla'],
            ':duracion_bruta_segundos' => $data['duracion_bruta_segundos'],
            ':duracion_neta_segundos' => $data['duracion_neta_segundos'],
        ];
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
            descuento_segundos INT UNSIGNED NOT NULL DEFAULT 0,
            diagnostico TEXT NULL,
            falla TEXT NULL,
            duracion_bruta_segundos INT UNSIGNED NULL,
            duracion_neta_segundos INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_crm_pedidos_servicio_empresa_numero (empresa_id, numero),
            KEY idx_crm_pedidos_servicio_empresa_fecha (empresa_id, fecha_inicio),
            KEY idx_crm_pedidos_servicio_cliente (empresa_id, cliente_id),
            KEY idx_crm_pedidos_servicio_articulo (empresa_id, articulo_id),
            KEY idx_crm_pedidos_servicio_clasificacion (empresa_id, clasificacion_codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
}
