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
        int $offset = 0
    ): array
    {
        $sql = 'SELECT * FROM empresas';
        $params = [];
        $this->applySearch($sql, $params, $search, $field);
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

    public function countAll(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM empresas')->fetchColumn();
    }

    public function countFiltered(?string $search = null, string $field = 'all'): int
    {
        $sql = 'SELECT COUNT(*) FROM empresas';
        $params = [];
        $this->applySearch($sql, $params, $search, $field);

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

        $sql = 'SELECT id, codigo, nombre, slug, razon_social, cuit, activa FROM empresas';
        $params = [];
        $this->applySearch($sql, $params, $search, $field);
        $sql .= ' ORDER BY nombre ASC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function applySearch(string &$sql, array &$params, ?string $search, string $field): void
    {
        $search = trim((string) $search);

        if ($search == '') {
            return;
        }

        $term = '%' . $search . '%';

        if ($field !== 'all' && isset(self::SEARCHABLE_FIELDS[$field])) {
            $sql .= ' WHERE ' . self::SEARCHABLE_FIELDS[$field] . ' LIKE :search';
            $params[':search'] = $term;
            return;
        }

        $conditions = [];
        foreach (self::SEARCHABLE_FIELDS as $key => $column) {
            $placeholder = ':search_' . $key;
            $conditions[] = $column . ' LIKE ' . $placeholder;
            $params[$placeholder] = $term;
        }

        $sql .= ' WHERE (' . implode(' OR ', $conditions) . ')';
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
        $sql = "INSERT INTO empresas (codigo, nombre, razon_social, cuit, slug, activa, modulo_tiendas, modulo_crm) 
                VALUES (:codigo, :nombre, :razon_social, :cuit, :slug, :activa, :modulo_tiendas, :modulo_crm)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':codigo' => $empresa->codigo,
            ':nombre' => $empresa->nombre,
            ':razon_social' => $empresa->razon_social,
            ':cuit' => $empresa->cuit,
            ':slug' => $empresa->slug,
            ':activa' => $empresa->activa,
            ':modulo_tiendas' => $empresa->modulo_tiendas,
            ':modulo_crm' => $empresa->modulo_crm,
        ]);
        
        $empresa->id = (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?Empresa
    {
        $stmt = $this->db->prepare("SELECT * FROM empresas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $empresa = $stmt->fetchObject(Empresa::class);
        return $empresa ?: null;
    }

    public function findByCodigo(string $codigo, ?int $excludeId = null): ?Empresa
    {
        $sql = "SELECT * FROM empresas WHERE codigo = :codigo";
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
        $sql = "SELECT * FROM empresas WHERE slug = :slug";
        $params = [':slug' => $slug];

        if ($excludeId !== null) {
            $sql .= " AND id != :excludeId";
            $params[':excludeId'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $empresa = $stmt->fetchObject(Empresa::class);

        return $empresa ?: null;
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
                modulo_crm = :modulo_crm 
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
            ':modulo_crm' => $empresa->modulo_crm,
            ':id' => $empresa->id,
        ]);
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
