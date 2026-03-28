<?php
declare(strict_types=1);

namespace App\Modules\Articulos;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

class ArticuloRepository
{
    private const JOIN_COLLATION = 'utf8mb4_unicode_ci';
    private const SEARCHABLE_FIELDS = [
        'id' => 'CAST(a.id AS CHAR)',
        'codigo_externo' => 'a.codigo_externo',
        'nombre' => 'a.nombre',
        'descripcion' => 'a.descripcion',
    ];

    private PDO $db;
    private string $articulosTable;
    private string $categoriaMapTable;
    private string $imagenesTable;
    private string $configTable;

    public function __construct(array $tables = [])
    {
        $this->db = Database::getConnection();
        $this->articulosTable = $this->normalizeTableName($tables['articulos'] ?? 'articulos');
        $this->categoriaMapTable = $this->normalizeTableName($tables['categoria_map'] ?? 'articulo_categoria_map');
        $this->imagenesTable = $this->normalizeTableName($tables['imagenes'] ?? 'articulo_imagenes');
        $this->configTable = $this->normalizeTableName($tables['config'] ?? 'empresa_config');

        if (!empty($tables['bootstrap']) && is_array($tables['bootstrap'])) {
            $this->ensureSchema($tables['bootstrap']);
        }
    }

    public static function forCrm(): self
    {
        return new self([
            'articulos' => 'crm_articulos',
            'categoria_map' => 'crm_articulo_categoria_map',
            'imagenes' => 'crm_articulo_imagenes',
            'config' => 'empresa_config_crm',
            'bootstrap' => [
                'articulos' => 'articulos',
                'categoria_map' => 'articulo_categoria_map',
                'imagenes' => 'articulo_imagenes',
            ],
        ]);
    }

    /**
     * Inserta un articulo o lo actualiza si el codigo externo ya existe en la misma empresa.
     * @return array{affected_rows:int}
     */
    public function upsert(Articulo $articulo): array
    {
        $sql = 'INSERT INTO ' . $this->quoteTable($this->articulosTable) . ' (empresa_id, codigo_externo, nombre, descripcion, precio, activo, fecha_ultima_sync)
                VALUES (:empresa_id, :codigo, :nombre, :descripcion, :precio, :activo, NOW())
                ON DUPLICATE KEY UPDATE
                    nombre = VALUES(nombre),
                    descripcion = VALUES(descripcion),
                    precio = VALUES(precio),
                    activo = VALUES(activo),
                    fecha_ultima_sync = NOW()';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $articulo->empresa_id,
            ':codigo' => $articulo->codigo_externo,
            ':nombre' => $articulo->nombre,
            ':descripcion' => $articulo->descripcion,
            ':precio' => $articulo->precio,
            ':activo' => $articulo->activo,
        ]);

        return [
            'affected_rows' => $stmt->rowCount(),
        ];
    }

    public function countAll(int $empresaId, string $search = '', string $field = 'all', ?int $categoriaId = null): int
    {
        $sql = 'SELECT COUNT(*)
            FROM ' . $this->quoteTable($this->articulosTable) . ' a
            LEFT JOIN ' . $this->quoteTable($this->categoriaMapTable) . ' acm
                ON acm.empresa_id = a.empresa_id
                AND ' . $this->buildSkuJoinCondition('a', 'acm') . '
            LEFT JOIN categorias c
                ON c.id = acm.categoria_id
                AND c.empresa_id = a.empresa_id
            WHERE a.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];

        $this->applyCategoriaFilter($sql, $params, $categoriaId);
        $this->applySearch($sql, $params, $search, $field, true);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findAllPaginated(
        int $empresaId,
        int $page = 1,
        int $limit = 50,
        string $search = '',
        string $field = 'all',
        string $orderBy = 'nombre',
        string $orderDir = 'ASC',
        ?int $categoriaId = null
    ): array {
        $offset = max(0, ($page - 1) * $limit);
        $sql = 'SELECT a.*, c.id AS categoria_id, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
                COALESCE(
                    (SELECT ruta FROM ' . $this->quoteTable($this->imagenesTable) . '
                        WHERE articulo_id = a.id AND empresa_id = a.empresa_id AND es_principal = 1
                        ORDER BY orden ASC LIMIT 1),
                    (SELECT imagen_default_producto FROM ' . $this->quoteTable($this->configTable) . ' WHERE empresa_id = a.empresa_id)
                ) AS imagen_principal
            FROM ' . $this->quoteTable($this->articulosTable) . ' a
            LEFT JOIN ' . $this->quoteTable($this->categoriaMapTable) . ' acm
                ON acm.empresa_id = a.empresa_id
                AND ' . $this->buildSkuJoinCondition('a', 'acm') . '
            LEFT JOIN categorias c
                ON c.id = acm.categoria_id
                AND c.empresa_id = a.empresa_id
            WHERE a.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];

        $this->applyCategoriaFilter($sql, $params, $categoriaId);
        $this->applySearch($sql, $params, $search, $field, true);

        $allowedColumns = ['codigo_externo', 'nombre', 'precio_lista_1', 'precio_lista_2', 'stock_actual', 'activo', 'fecha_ultima_sync', 'categoria_nombre'];
        if (!in_array($orderBy, $allowedColumns, true)) {
            $orderBy = 'nombre';
        }

        $orderColumn = $orderBy === 'categoria_nombre' ? 'categoria_nombre' : 'a.' . $orderBy;
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql .= " ORDER BY {$orderColumn} {$orderDir}, a.nombre ASC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countPublicCatalog(int $empresaId, string $search = '', ?string $categoriaSlug = null): int
    {
        $sql = 'SELECT COUNT(*)
            FROM ' . $this->quoteTable($this->articulosTable) . ' a
            LEFT JOIN ' . $this->quoteTable($this->categoriaMapTable) . ' acm
                ON acm.empresa_id = a.empresa_id
                AND ' . $this->buildSkuJoinCondition('a', 'acm') . '
            LEFT JOIN categorias c
                ON c.id = acm.categoria_id
                AND c.empresa_id = a.empresa_id
            WHERE a.empresa_id = :empresa_id
                AND a.activo = 1';
        $params = [':empresa_id' => $empresaId];

        $this->applyCategoriaSlugFilter($sql, $params, $categoriaSlug);
        $this->applySearch($sql, $params, $search, 'all', true);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findPublicCatalogPaginated(
        int $empresaId,
        int $page = 1,
        int $limit = 24,
        string $search = '',
        ?string $categoriaSlug = null
    ): array {
        $offset = max(0, ($page - 1) * $limit);
        $sql = 'SELECT a.*, c.id AS categoria_id, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
                COALESCE(
                    (SELECT ruta FROM ' . $this->quoteTable($this->imagenesTable) . '
                        WHERE articulo_id = a.id AND empresa_id = a.empresa_id AND es_principal = 1
                        ORDER BY orden ASC LIMIT 1),
                    (SELECT imagen_default_producto FROM ' . $this->quoteTable($this->configTable) . ' WHERE empresa_id = a.empresa_id)
                ) AS imagen_principal
            FROM ' . $this->quoteTable($this->articulosTable) . ' a
            LEFT JOIN ' . $this->quoteTable($this->categoriaMapTable) . ' acm
                ON acm.empresa_id = a.empresa_id
                AND ' . $this->buildSkuJoinCondition('a', 'acm') . '
            LEFT JOIN categorias c
                ON c.id = acm.categoria_id
                AND c.empresa_id = a.empresa_id
            WHERE a.empresa_id = :empresa_id
                AND a.activo = 1';
        $params = [':empresa_id' => $empresaId];

        $this->applyCategoriaSlugFilter($sql, $params, $categoriaSlug);
        $this->applySearch($sql, $params, $search, 'all', true);

        $sql .= ' ORDER BY a.nombre ASC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findSuggestions(int $empresaId, string $search = '', string $field = 'all', int $limit = 3): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $sql = 'SELECT a.id, a.codigo_externo, a.nombre, a.descripcion FROM ' . $this->quoteTable($this->articulosTable) . ' a WHERE a.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];
        $this->applySearch($sql, $params, $search, $field, true);
        $sql .= ' ORDER BY a.nombre ASC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function applySearch(string &$sql, array &$params, string $search = '', string $field = 'all', bool $hasWhere = false): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $operator = $hasWhere ? ' AND ' : ' WHERE ';

        if ($field !== 'all' && isset(self::SEARCHABLE_FIELDS[$field])) {
            $params[':search'] = '%' . $search . '%';
            $sql .= $operator . '(' . self::SEARCHABLE_FIELDS[$field] . ' LIKE :search)';
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

    private function applyCategoriaFilter(string &$sql, array &$params, ?int $categoriaId): void
    {
        if ($categoriaId === null || $categoriaId <= 0) {
            return;
        }

        $sql .= ' AND acm.categoria_id = :categoria_id';
        $params[':categoria_id'] = $categoriaId;
    }

    private function applyCategoriaSlugFilter(string &$sql, array &$params, ?string $categoriaSlug): void
    {
        $categoriaSlug = trim((string) $categoriaSlug);
        if ($categoriaSlug === '') {
            return;
        }

        $sql .= ' AND c.slug = :categoria_slug AND c.activa = 1 AND c.visible_store = 1';
        $params[':categoria_slug'] = $categoriaSlug;
    }

    public function truncateArticulos(int $empresaId): void
    {
        $stmt = $this->db->prepare('DELETE FROM ' . $this->quoteTable($this->articulosTable) . ' WHERE empresa_id = :empresa_id');
        $stmt->execute([':empresa_id' => $empresaId]);
    }

    public function deleteByIds(array $ids, int $empresaId): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = 'DELETE FROM ' . $this->quoteTable($this->articulosTable) . " WHERE empresa_id = ? AND id IN ($placeholders)";

        $params = array_merge([$empresaId], $ids);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function findById(int $id, int $empresaId): ?Articulo
    {
        $sql = 'SELECT a.*, c.id AS categoria_id, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
                COALESCE(
                    (SELECT ruta FROM ' . $this->quoteTable($this->imagenesTable) . '
                        WHERE articulo_id = a.id AND empresa_id = a.empresa_id AND es_principal = 1
                        ORDER BY orden ASC LIMIT 1),
                    (SELECT imagen_default_producto FROM ' . $this->quoteTable($this->configTable) . ' WHERE empresa_id = a.empresa_id)
                ) AS imagen_principal
            FROM ' . $this->quoteTable($this->articulosTable) . ' a
            LEFT JOIN ' . $this->quoteTable($this->categoriaMapTable) . ' acm
                ON acm.empresa_id = a.empresa_id
                AND ' . $this->buildSkuJoinCondition('a', 'acm') . '
            LEFT JOIN categorias c
                ON c.id = acm.categoria_id
                AND c.empresa_id = a.empresa_id
            WHERE a.id = :id AND a.empresa_id = :empresa_id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $articulo = new Articulo();
        $articulo->id = (int) $row['id'];
        $articulo->empresa_id = (int) $row['empresa_id'];
        $articulo->codigo_externo = (string) $row['codigo_externo'];
        $articulo->nombre = (string) $row['nombre'];
        $articulo->descripcion = $row['descripcion'];
        $articulo->precio = $row['precio'] !== null ? (float) $row['precio'] : null;
        $articulo->precio_lista_1 = $row['precio_lista_1'] !== null ? (float) $row['precio_lista_1'] : null;
        $articulo->precio_lista_2 = $row['precio_lista_2'] !== null ? (float) $row['precio_lista_2'] : null;
        $articulo->stock_actual = $row['stock_actual'] !== null ? (float) $row['stock_actual'] : null;
        $articulo->activo = (int) $row['activo'];
        $articulo->categoria_id = isset($row['categoria_id']) && $row['categoria_id'] !== null ? (int) $row['categoria_id'] : null;
        $articulo->categoria_nombre = $row['categoria_nombre'] ?? null;
        $articulo->categoria_slug = $row['categoria_slug'] ?? null;
        $articulo->fecha_ultima_sync = $row['fecha_ultima_sync'];
        $articulo->imagen_principal = $row['imagen_principal'] ?? null;

        return $articulo;
    }

    public function update(Articulo $articulo): bool
    {
        $sql = 'UPDATE ' . $this->quoteTable($this->articulosTable) . ' SET
                nombre = :nombre,
                descripcion = :descripcion,
                precio = :precio,
                precio_lista_1 = :precio_lista_1,
                precio_lista_2 = :precio_lista_2,
                stock_actual = :stock_actual,
                activo = :activo
                WHERE id = :id AND empresa_id = :empresa_id';
        $stmt = $this->db->prepare($sql);
        $updated = $stmt->execute([
            ':nombre' => $articulo->nombre,
            ':descripcion' => $articulo->descripcion,
            ':precio' => $articulo->precio,
            ':precio_lista_1' => $articulo->precio_lista_1,
            ':precio_lista_2' => $articulo->precio_lista_2,
            ':stock_actual' => $articulo->stock_actual,
            ':activo' => $articulo->activo,
            ':id' => $articulo->id,
            ':empresa_id' => $articulo->empresa_id,
        ]);

        if ($updated) {
            $this->syncCategoriaMapping($articulo->empresa_id, $articulo->codigo_externo, $articulo->categoria_id);
        }

        return $updated;
    }

    public function syncCategoriaMapping(int $empresaId, string $codigoExterno, ?int $categoriaId): void
    {
        $deleteStmt = $this->db->prepare('DELETE FROM ' . $this->quoteTable($this->categoriaMapTable) . ' WHERE empresa_id = :empresa_id AND articulo_codigo_externo = :codigo_externo');
        $deleteStmt->execute([
            ':empresa_id' => $empresaId,
            ':codigo_externo' => $codigoExterno,
        ]);

        if ($categoriaId === null || $categoriaId <= 0) {
            return;
        }

        $insertStmt = $this->db->prepare('INSERT INTO ' . $this->quoteTable($this->categoriaMapTable) . ' (empresa_id, articulo_codigo_externo, categoria_id)
            VALUES (:empresa_id, :codigo_externo, :categoria_id)');
        $insertStmt->execute([
            ':empresa_id' => $empresaId,
            ':codigo_externo' => $codigoExterno,
            ':categoria_id' => $categoriaId,
        ]);
    }

    public function updatePrecioListas(string $sku, float $precio, string $columna, int $empresaId): int
    {
        if (!in_array($columna, ['precio_lista_1', 'precio_lista_2'], true)) {
            return 0;
        }

        $sql = 'UPDATE ' . $this->quoteTable($this->articulosTable) . " SET {$columna} = :precio, fecha_ultima_sync = NOW() WHERE codigo_externo = :sku AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':precio' => $precio,
            ':sku' => $sku,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->rowCount();
    }

    public function updateStock(string $sku, float $saldo, int $empresaId): int
    {
        $sql = 'UPDATE ' . $this->quoteTable($this->articulosTable) . ' SET stock_actual = :saldo, fecha_ultima_sync = NOW() WHERE codigo_externo = :sku AND empresa_id = :empresa_id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':saldo' => $saldo,
            ':sku' => $sku,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->rowCount();
    }

    public function guardarImagen(int $empresaId, int $articuloId, string $ruta, int $esPrincipal = 1): bool
    {
        $sql = 'INSERT INTO ' . $this->quoteTable($this->imagenesTable) . ' (empresa_id, articulo_id, ruta, es_principal)
                VALUES (:empresa_id, :articulo_id, :ruta, :es_principal)';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':empresa_id' => $empresaId,
            ':articulo_id' => $articuloId,
            ':ruta' => $ruta,
            ':es_principal' => $esPrincipal,
        ]);
    }

    public function obtenerImagenPrincipal(int $empresaId, int $articuloId): ?string
    {
        $sql = 'SELECT ruta FROM ' . $this->quoteTable($this->imagenesTable) . '
                WHERE empresa_id = :empresa_id AND articulo_id = :articulo_id
                ORDER BY es_principal DESC, orden ASC
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId, ':articulo_id' => $articuloId]);
        $ruta = $stmt->fetchColumn();
        return $ruta ?: null;
    }

    public function obtenerImagenesArticulo(int $empresaId, int $articuloId): array
    {
        $sql = 'SELECT id, ruta, es_principal, orden
                FROM ' . $this->quoteTable($this->imagenesTable) . '
                WHERE empresa_id = :empresa_id AND articulo_id = :articulo_id
                ORDER BY es_principal DESC, orden ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId, ':articulo_id' => $articuloId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function eliminarImagen(int $empresaId, int $articuloId, int $imagenId): bool
    {
        $sql = 'DELETE FROM ' . $this->quoteTable($this->imagenesTable) . '
                WHERE id = :id AND empresa_id = :empresa_id AND articulo_id = :articulo_id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $imagenId,
            ':empresa_id' => $empresaId,
            ':articulo_id' => $articuloId,
        ]);
    }

    public function marcarImagenPrincipal(int $empresaId, int $articuloId, int $imagenId): void
    {
        $sql = 'UPDATE ' . $this->quoteTable($this->imagenesTable) . ' SET es_principal = 0 WHERE empresa_id = :empresa_id AND articulo_id = :articulo_id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId, ':articulo_id' => $articuloId]);

        $sql2 = 'UPDATE ' . $this->quoteTable($this->imagenesTable) . ' SET es_principal = 1 WHERE id = :id AND empresa_id = :empresa_id AND articulo_id = :articulo_id';
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute([':id' => $imagenId, ':empresa_id' => $empresaId, ':articulo_id' => $articuloId]);
    }

    private function ensureSchema(array $bootstrap): void
    {
        $pairs = [
            [$this->articulosTable, $bootstrap['articulos'] ?? 'articulos'],
            [$this->categoriaMapTable, $bootstrap['categoria_map'] ?? 'articulo_categoria_map'],
            [$this->imagenesTable, $bootstrap['imagenes'] ?? 'articulo_imagenes'],
        ];

        foreach ($pairs as [$target, $source]) {
            $target = $this->normalizeTableName($target);
            $source = $this->normalizeTableName($source);

            if ($target === $source) {
                continue;
            }

            $this->db->exec(sprintf(
                'CREATE TABLE IF NOT EXISTS %s LIKE %s',
                $this->quoteTable($target),
                $this->quoteTable($source)
            ));
        }
    }

    private function normalizeTableName(string $table): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException('Nombre de tabla invalido.');
        }

        return $table;
    }

    private function quoteTable(string $table): string
    {
        return '`' . $this->normalizeTableName($table) . '`';
    }

    private function buildSkuJoinCondition(string $articuloAlias, string $mapAlias): string
    {
        return sprintf(
            '%s.codigo_externo COLLATE %s = %s.articulo_codigo_externo COLLATE %s',
            $articuloAlias,
            self::JOIN_COLLATION,
            $mapAlias,
            self::JOIN_COLLATION
        );
    }
}
