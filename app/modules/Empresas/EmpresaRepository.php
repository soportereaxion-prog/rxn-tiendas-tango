<?php

declare(strict_types=1);

namespace App\Modules\Empresas;

use App\Core\Database;
use PDO;

class EmpresaRepository
{
    private PDO $db;
    private const SEARCHABLE_FIELDS = [
        'id' => 'CAST(id AS CHAR)',
        'codigo' => 'codigo',
        'nombre' => 'nombre',
        'slug' => 'slug',
        'razon_social' => 'razon_social',
        'cuit' => 'cuit',
    ];
    private const SORTABLE_FIELDS = [
        'id' => 'id',
        'codigo' => 'codigo',
        'nombre' => 'nombre',
        'slug' => 'slug',
        'razon_social' => 'razon_social',
        'cuit' => 'cuit',
        'activa' => 'activa',
    ];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAll(
        ?string $search = null,
        string $field = 'all',
        string $sort = 'nombre',
        string $dir = 'asc',
        int $limit = 10,
        int $offset = 0,
        bool $onlyDeleted = false
    ): array
    {
        $delCond = $onlyDeleted ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
        $sql = "SELECT * FROM empresas WHERE $delCond";
        $params = [];
        $this->applySearch($sql, $params, $search, $field, true);
        $sql .= sprintf(
            ' ORDER BY %s %s LIMIT :limit OFFSET :offset',
            $this->normalizeSortField($sort),
            $this->normalizeSortDirection($dir)
        );

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, Empresa::class);
    }

    public function countAll(bool $onlyDeleted = false): int
    {
        $delCond = $onlyDeleted ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
        return (int) $this->db->query("SELECT COUNT(*) FROM empresas WHERE $delCond")->fetchColumn();
    }

    public function countFiltered(?string $search = null, string $field = 'all', bool $onlyDeleted = false): int
    {
        $delCond = $onlyDeleted ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
        $sql = "SELECT COUNT(*) FROM empresas WHERE $delCond";
        $params = [];
        $this->applySearch($sql, $params, $search, $field, true);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findSuggestions(?string $search = null, string $field = 'all', int $limit = 3): array
    {
        $search = trim((string) $search);

        if ($search === '') {
            return [];
        }

        $sql = 'SELECT id, codigo, nombre, slug, razon_social, cuit, activa FROM empresas WHERE deleted_at IS NULL';
        $params = [];
        $this->applySearch($sql, $params, $search, $field, true);
        $sql .= ' ORDER BY nombre ASC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function applySearch(string &$sql, array &$params, ?string $search, string $field, bool $hasWhere = false): void
    {
        $search = trim((string) $search);

        if ($search == '') {
            return;
        }

        $term = '%' . $search . '%';

        $operator = $hasWhere ? ' AND ' : ' WHERE ';

        if ($field !== 'all' && isset(self::SEARCHABLE_FIELDS[$field])) {
            $sql .= $operator . self::SEARCHABLE_FIELDS[$field] . ' LIKE :search';
            $params[':search'] = $term;
            return;
        }

        $conditions = [];
        foreach (self::SEARCHABLE_FIELDS as $key => $column) {
            $placeholder = ':search_' . $key;
            $conditions[] = $column . ' LIKE ' . $placeholder;
            $params[$placeholder] = $term;
        }

        $sql .= $operator . '(' . implode(' OR ', $conditions) . ')';
    }

    private function normalizeSortField(string $field): string
    {
        return self::SORTABLE_FIELDS[$field] ?? self::SORTABLE_FIELDS['nombre'];
    }

    private function normalizeSortDirection(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
    }

    public function save(Empresa $empresa): void
    {
        $sql = "INSERT INTO empresas (codigo, nombre, razon_social, cuit, slug, activa, modulo_tiendas, tiendas_modulo_notas, modulo_crm, crm_modulo_notas) 
                VALUES (:codigo, :nombre, :razon_social, :cuit, :slug, :activa, :modulo_tiendas, :tiendas_modulo_notas, :modulo_crm, :crm_modulo_notas)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':codigo' => $empresa->codigo,
            ':nombre' => $empresa->nombre,
            ':razon_social' => $empresa->razon_social,
            ':cuit' => $empresa->cuit,
            ':slug' => $empresa->slug,
            ':activa' => $empresa->activa,
            ':modulo_tiendas' => $empresa->modulo_tiendas,
            ':tiendas_modulo_notas' => $empresa->tiendas_modulo_notas,
            ':modulo_crm' => $empresa->modulo_crm,
            ':crm_modulo_notas' => $empresa->crm_modulo_notas,
        ]);
        
        $empresa->id = (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?Empresa
    {
        $stmt = $this->db->prepare("SELECT * FROM empresas WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute([':id' => $id]);
        $empresa = $stmt->fetchObject(Empresa::class);
        return $empresa ?: null;
    }

    public function findByCodigo(string $codigo, ?int $excludeId = null): ?Empresa
    {
        $sql = "SELECT * FROM empresas WHERE codigo = :codigo AND deleted_at IS NULL";
        $params = [':codigo' => $codigo];
        
        if ($excludeId !== null) {
            $sql .= " AND id != :excludeId";
            $params[':excludeId'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $empresa = $stmt->fetchObject(Empresa::class);
        return $empresa ?: null;
    }

    public function findBySlug(string $slug, ?int $excludeId = null): ?Empresa
    {
        $sql = "SELECT * FROM empresas WHERE slug = :slug AND deleted_at IS NULL";
        $params = [':slug' => $slug];

        if ($excludeId !== null) {
            $sql .= " AND id != :excludeId";
            $params[':excludeId'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->_mapRowToEntity($row) : null;
    }

    private function _mapRowToEntity(array $row): Empresa
    {
        $empresa = new Empresa();
        $empresa->id = (int)$row['id'];
        $empresa->codigo = $row['codigo'];
        $empresa->nombre = $row['nombre'];
        $empresa->razon_social = $row['razon_social'];
        $empresa->cuit = $row['cuit'];
        $empresa->slug = $row['slug'] ?? null;
        $empresa->activa = (int)$row['activa'];
        $empresa->modulo_tiendas = (int)$row['modulo_tiendas'];
        $empresa->tiendas_modulo_notas = isset($row['tiendas_modulo_notas']) ? (int)$row['tiendas_modulo_notas'] : 0;
        $empresa->modulo_crm = (int)$row['modulo_crm'];
        $empresa->crm_modulo_notas = isset($row['crm_modulo_notas']) ? (int)$row['crm_modulo_notas'] : 0;
        $empresa->created_at = $row['created_at'] ?? null;
        $empresa->updated_at = $row['updated_at'] ?? null;
        return $empresa;
    }

    public function update(Empresa $empresa): void
    {
        $sql = "UPDATE empresas SET 
                codigo = :codigo, 
                nombre = :nombre, 
                razon_social = :razon_social, 
                cuit = :cuit, 
                slug = :slug,
                activa = :activa,
                modulo_tiendas = :modulo_tiendas,
                tiendas_modulo_notas = :tiendas_modulo_notas,
                modulo_crm = :modulo_crm,
                crm_modulo_notas = :crm_modulo_notas
                WHERE id = :id";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':codigo' => $empresa->codigo,
            ':nombre' => $empresa->nombre,
            ':razon_social' => $empresa->razon_social,
            ':cuit' => $empresa->cuit,
            ':slug' => $empresa->slug,
            ':activa' => $empresa->activa,
            ':modulo_tiendas' => $empresa->modulo_tiendas,
            ':tiendas_modulo_notas' => $empresa->tiendas_modulo_notas,
            ':modulo_crm' => $empresa->modulo_crm,
            ':crm_modulo_notas' => $empresa->crm_modulo_notas,
            ':id' => $empresa->id,
        ]);
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE empresas SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function restoreById(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE empresas SET deleted_at = NULL WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function forceDeleteById(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM empresas WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function updateBranding(int $id, array $data): void
    {
        $sql = "UPDATE empresas SET 
                logo_url = :logo,
                favicon_url = :favicon,
                color_primary = :c_prim,
                color_secondary = :c_sec,
                footer_text = :f_text,
                footer_address = :f_addr,
                footer_phone = :f_phone,
                footer_socials = :f_soc
                WHERE id = :id";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':logo' => $data['logo_url'] ?? null,
            ':favicon' => $data['favicon_url'] ?? null,
            ':c_prim' => $data['color_primary'] ?? null,
            ':c_sec' => $data['color_secondary'] ?? null,
            ':f_text' => $data['footer_text'] ?? null,
            ':f_addr' => $data['footer_address'] ?? null,
            ':f_phone' => $data['footer_phone'] ?? null,
            ':f_soc' => $data['footer_socials'] ?? null,
            ':id' => $id,
        ]);
    }
}
