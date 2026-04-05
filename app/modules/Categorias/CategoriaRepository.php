<?php
declare(strict_types=1);

namespace App\Modules\Categorias;

use App\Core\Database;
use PDO;

class CategoriaRepository
{
    private const JOIN_COLLATION = 'utf8mb4_unicode_ci';
    private const SEARCHABLE_FIELDS = [
        'nombre' => 'c.nombre',
        'slug' => 'c.slug',
        'descripcion_corta' => 'c.descripcion_corta',
    ];

    private const SORTABLE_FIELDS = [
        'nombre' => 'c.nombre',
        'slug' => 'c.slug',
        'orden_visual' => 'c.orden_visual',
        'activa' => 'c.activa',
        'visible_store' => 'c.visible_store',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ensureSoftDeleteSchema();
    }

    private function ensureSoftDeleteSchema(): void
    {
        try {
            $this->db->exec('ALTER TABLE categorias ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL');
        } catch (\PDOException $e) {
            // Ignorar si la columna ya existe
        }
    }

    public function countAllByEmpresaId(int $empresaId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM categorias WHERE empresa_id = :empresa_id');
        $stmt->execute([':empresa_id' => $empresaId]);

        return (int) $stmt->fetchColumn();
    }

    public function countFilteredByEmpresaId(int $empresaId, string $search = '', string $field = 'all', bool $onlyDeleted = false, array $advancedFilters = []): int
    {
        $delCond = $onlyDeleted ? 'c.deleted_at IS NOT NULL' : 'c.deleted_at IS NULL';
        $sql = 'SELECT COUNT(*) FROM categorias c WHERE c.empresa_id = :empresa_id AND ' . $delCond;
        $params = [':empresa_id' => $empresaId];
        $this->applySearch($sql, $params, $search, $field, true);

        list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
            'nombre' => 'c.nombre',
            'slug' => 'c.slug',
            'orden_visual' => 'CAST(c.orden_visual AS CHAR)',
            'activa' => 'CAST(c.activa AS CHAR)',
            'visible_store' => 'CAST(c.visible_store AS CHAR)',
        ]);
        if ($advSql !== '') {
            $sql .= ' AND (' . $advSql . ')';
            $params = array_merge($params, $advParams);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findFilteredPaginatedByEmpresaId(
        int $empresaId,
        string $search = '',
        string $field = 'all',
        string $sort = 'orden_visual',
        string $dir = 'asc',
        int $limit = 10,
        int $offset = 0,
        bool $onlyDeleted = false,
        array $advancedFilters = []
    ): array {
        $delCond = $onlyDeleted ? 'c.deleted_at IS NOT NULL' : 'c.deleted_at IS NULL';
        $sql = 'SELECT c.*, COUNT(DISTINCT a.id) AS articulos_count
            FROM categorias c
            LEFT JOIN articulo_categoria_map acm
                ON acm.categoria_id = c.id
                AND acm.empresa_id = c.empresa_id
            LEFT JOIN articulos a
                ON a.empresa_id = acm.empresa_id
                AND ' . $this->buildSkuJoinCondition('a', 'acm') . '
                AND a.deleted_at IS NULL
            WHERE c.empresa_id = :empresa_id AND ' . $delCond;
        $params = [':empresa_id' => $empresaId];

        $this->applySearch($sql, $params, $search, $field, true);

        list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
            'nombre' => 'c.nombre',
            'slug' => 'c.slug',
            'orden_visual' => 'CAST(c.orden_visual AS CHAR)',
            'activa' => 'CAST(c.activa AS CHAR)',
            'visible_store' => 'CAST(c.visible_store AS CHAR)',
        ]);
        if ($advSql !== '') {
            $sql .= ' AND (' . $advSql . ')';
            $params = array_merge($params, $advParams);
        }

        $sortColumn = self::SORTABLE_FIELDS[$sort] ?? self::SORTABLE_FIELDS['orden_visual'];
        $direction = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';

        $sql .= ' GROUP BY c.id';
        $sql .= sprintf(' ORDER BY %s %s, c.nombre ASC LIMIT :limit OFFSET :offset', $sortColumn, $direction);

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'hydrateCategoria'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function findSuggestionsByEmpresaId(int $empresaId, string $search = '', string $field = 'all', int $limit = 3): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $sql = 'SELECT c.id, c.nombre, c.slug, c.descripcion_corta FROM categorias c WHERE c.empresa_id = :empresa_id AND c.deleted_at IS NULL';
        $params = [':empresa_id' => $empresaId];
        $this->applySearch($sql, $params, $search, $field, true);
        $sql .= ' ORDER BY c.nombre ASC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByIdAndEmpresaId(int $id, int $empresaId, bool $includeDeleted = false): ?Categoria
    {
        $sql = 'SELECT c.*, COUNT(DISTINCT a.id) AS articulos_count
            FROM categorias c
            LEFT JOIN articulo_categoria_map acm
                ON acm.categoria_id = c.id
                AND acm.empresa_id = c.empresa_id
            LEFT JOIN articulos a
                ON a.empresa_id = acm.empresa_id
                AND ' . $this->buildSkuJoinCondition('a', 'acm') . '
                AND a.deleted_at IS NULL
            WHERE c.id = :id AND c.empresa_id = :empresa_id';
        
        if (!$includeDeleted) {
            $sql .= ' AND c.deleted_at IS NULL';
        }

        $sql .= ' GROUP BY c.id LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateCategoria($row) : null;
    }

    public function findBySlug(int $empresaId, string $slug, ?int $excludeId = null, bool $includeDeleted = false): ?Categoria
    {
        $sql = 'SELECT * FROM categorias WHERE empresa_id = :empresa_id AND slug = :slug';
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $params = [
            ':empresa_id' => $empresaId,
            ':slug' => $slug,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateCategoria($row) : null;
    }

    public function save(Categoria $categoria): void
    {
        if ($categoria->id === null) {
            $sql = 'INSERT INTO categorias
                (empresa_id, nombre, slug, descripcion_corta, imagen_portada, orden_visual, activa, visible_store)
                VALUES
                (:empresa_id, :nombre, :slug, :descripcion_corta, :imagen_portada, :orden_visual, :activa, :visible_store)';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':empresa_id' => $categoria->empresa_id,
                ':nombre' => $categoria->nombre,
                ':slug' => $categoria->slug,
                ':descripcion_corta' => $categoria->descripcion_corta,
                ':imagen_portada' => $categoria->imagen_portada,
                ':orden_visual' => $categoria->orden_visual,
                ':activa' => $categoria->activa,
                ':visible_store' => $categoria->visible_store,
            ]);

            $categoria->id = (int) $this->db->lastInsertId();
            return;
        }

        $sql = 'UPDATE categorias SET
            nombre = :nombre,
            slug = :slug,
            descripcion_corta = :descripcion_corta,
            imagen_portada = :imagen_portada,
            orden_visual = :orden_visual,
            activa = :activa,
            visible_store = :visible_store
            WHERE id = :id AND empresa_id = :empresa_id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $categoria->id,
            ':empresa_id' => $categoria->empresa_id,
            ':nombre' => $categoria->nombre,
            ':slug' => $categoria->slug,
            ':descripcion_corta' => $categoria->descripcion_corta,
            ':imagen_portada' => $categoria->imagen_portada,
            ':orden_visual' => $categoria->orden_visual,
            ':activa' => $categoria->activa,
            ':visible_store' => $categoria->visible_store,
        ]);
    }

    public function findSelectableByEmpresaId(int $empresaId, bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM categorias WHERE empresa_id = :empresa_id AND deleted_at IS NULL';
        $params = [':empresa_id' => $empresaId];

        if ($onlyActive) {
            $sql .= ' AND activa = 1';
        }

        $sql .= ' ORDER BY orden_visual ASC, nombre ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'hydrateCategoria'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function findStoreCategoriesWithCounts(int $empresaId): array
    {
        $sql = 'SELECT c.*, COUNT(a.id) AS articulos_count
            FROM categorias c
            INNER JOIN articulo_categoria_map acm
                ON acm.categoria_id = c.id
                AND acm.empresa_id = c.empresa_id
            INNER JOIN articulos a
                ON a.empresa_id = acm.empresa_id
                AND ' . $this->buildSkuJoinCondition('a', 'acm') . '
                AND a.activo = 1
                AND a.deleted_at IS NULL
            WHERE c.empresa_id = :empresa_id
                AND c.activa = 1
                AND c.visible_store = 1
                AND c.deleted_at IS NULL
            GROUP BY c.id
            ORDER BY c.orden_visual ASC, c.nombre ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId]);

        return array_map([$this, 'hydrateCategoria'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
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

    private function hydrateCategoria(array $row): Categoria
    {
        $categoria = new Categoria();
        $categoria->id = isset($row['id']) ? (int) $row['id'] : null;
        $categoria->empresa_id = (int) ($row['empresa_id'] ?? 0);
        $categoria->nombre = (string) ($row['nombre'] ?? '');
        $categoria->slug = (string) ($row['slug'] ?? '');
        $categoria->descripcion_corta = isset($row['descripcion_corta']) ? (string) $row['descripcion_corta'] : null;
        $categoria->imagen_portada = isset($row['imagen_portada']) ? (string) $row['imagen_portada'] : null;
        $categoria->orden_visual = (int) ($row['orden_visual'] ?? 0);
        $categoria->activa = (int) ($row['activa'] ?? 1);
        $categoria->visible_store = (int) ($row['visible_store'] ?? 1);
        $categoria->articulos_count = (int) ($row['articulos_count'] ?? 0);
        $categoria->created_at = $row['created_at'] ?? null;
        $categoria->updated_at = $row['updated_at'] ?? null;

        return $categoria;
    }

    public function deleteByIds(array $ids, int $empresaId): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "UPDATE categorias SET deleted_at = NOW() WHERE empresa_id = ? AND id IN ($placeholders)";

        $params = array_merge([$empresaId], $ids);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function restoreByIds(array $ids, int $empresaId): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "UPDATE categorias SET deleted_at = NULL WHERE empresa_id = ? AND id IN ($placeholders)";

        $params = array_merge([$empresaId], $ids);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function forceDeleteByIds(array $ids, int $empresaId): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "DELETE FROM categorias WHERE empresa_id = ? AND id IN ($placeholders)";

        $params = array_merge([$empresaId], $ids);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
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
