<?php

declare(strict_types=1);

namespace App\Modules\Empresas;

use InvalidArgumentException;

class EmpresaService
{
    private const SEARCH_FIELDS = ['all', 'id', 'codigo', 'nombre', 'slug', 'razon_social', 'cuit'];
    private const SORT_FIELDS = ['id', 'codigo', 'nombre', 'slug', 'razon_social', 'cuit', 'activa'];
    private const SORT_DIRECTIONS = ['asc', 'desc'];
    private const PER_PAGE = 10;
    private const SUGGESTION_LIMIT = 3;
    private EmpresaRepository $repository;

    public function __construct()
    {
        $this->repository = new EmpresaRepository();
    }

    public function findAll(array $filters = [], array $advancedFilters = []): array
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $field = $this->normalizeSearchField($filters['field'] ?? 'all');
        $sort = $this->normalizeSortField($filters['sort'] ?? 'nombre');
        $dir = $this->normalizeSortDirection($filters['dir'] ?? 'asc');
        $status = $filters['status'] ?? 'activos';
        $onlyDeleted = $status === 'papelera';
        
        $total = $this->repository->countAll($onlyDeleted);
        $filteredTotal = $this->repository->countFiltered($search, $field, $onlyDeleted, $advancedFilters);
        $lastPage = max(1, (int) ceil($filteredTotal / self::PER_PAGE));
        $page = $this->normalizePage($filters['page'] ?? 1, $lastPage);
        $offset = ($page - 1) * self::PER_PAGE;

        return [
            'items' => $this->repository->findAll($search, $field, $sort, $dir, self::PER_PAGE, $offset, $onlyDeleted, $advancedFilters),
            'filters' => [
                'search' => $search,
                'field' => $field,
                'sort' => $sort,
                'dir' => $dir,
                'page' => $page,
                'status' => $status,
            ],
            'total' => $total,
            'filteredTotal' => $filteredTotal,
            'pagination' => [
                'page' => $page,
                'perPage' => self::PER_PAGE,
                'totalPages' => $lastPage,
                'hasPrevious' => $page > 1,
                'hasNext' => $page < $lastPage,
                'previousPage' => max(1, $page - 1),
                'nextPage' => min($lastPage, $page + 1),
            ],
        ];
    }

    public function findById(int $id, bool $includeDeleted = false): ?Empresa
    {
        if ($includeDeleted) {
            // Short raw query to not break repository methods if they aren't adapted
            $db = \App\Core\Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM empresas WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) return null;
            $empresa = new Empresa();
            foreach ($row as $k => $v) {
                if (property_exists($empresa, $k)) {
                    $empresa->$k = $v;
                }
            }
            return $empresa;
        }

        return $this->repository->findById($id);
    }

    public function findSuggestions(array $filters = []): array
    {
        $search = isset($filters['q']) ? trim((string) $filters['q']) : '';
        $field = $this->normalizeSearchField($filters['field'] ?? 'all');

        if (mb_strlen($search) < 2) {
            return [];
        }

        $suggestions = $this->repository->findSuggestions($search, $field, self::SUGGESTION_LIMIT);

        return array_map(function (array $row) use ($field): array {
            $slug = trim((string) ($row['slug'] ?? ''));
            $codigo = trim((string) ($row['codigo'] ?? ''));
            $nombre = trim((string) ($row['nombre'] ?? ''));
            $razonSocial = trim((string) ($row['razon_social'] ?? ''));
            $cuit = trim((string) ($row['cuit'] ?? ''));
            $extras = array_filter([
                $codigo !== '' ? $codigo : null,
                $slug !== '' ? $slug : null,
            ]);

            $value = match ($field) {
                'id' => (string) ((int) ($row['id'] ?? 0)),
                'codigo' => $codigo,
                'slug' => $slug,
                'razon_social' => $razonSocial,
                'cuit' => $cuit,
                default => $nombre,
            };

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $nombre,
                'value' => $value !== '' ? $value : $nombre,
                'caption' => trim('#' . (int) ($row['id'] ?? 0) . ' | ' . implode(' | ', $extras), ' |'),
                'activa' => !empty($row['activa']),
            ];
        }, $suggestions);
    }

    public function create(array $data): void
    {
        if (empty($data['codigo']) || empty($data['nombre'])) {
            throw new InvalidArgumentException('El código y el nombre son obligatorios.');
        }

        if ($this->repository->findByCodigo(trim($data['codigo']))) {
            throw new InvalidArgumentException('El código ya está en uso por otra empresa.');
        }

        $empresa = new Empresa();
        $empresa->codigo = trim($data['codigo']);
        $empresa->nombre = trim($data['nombre']);
        $empresa->titulo_pestana = !empty($data['titulo_pestana']) ? trim((string)$data['titulo_pestana']) : null;
        $empresa->razon_social = !empty($data['razon_social']) ? trim($data['razon_social']) : null;
        $empresa->cuit = !empty($data['cuit']) ? trim($data['cuit']) : null;
        $empresa->slug = $this->generateUniqueSlug($empresa->nombre);
        $empresa->activa = isset($data['activa']) ? 1 : 0;
        $empresa->modulo_tiendas = ($empresa->activa === 1 && isset($data['modulo_tiendas'])) ? 1 : 0;
        $empresa->tiendas_modulo_notas = ($empresa->modulo_tiendas === 1 && isset($data['tiendas_modulo_notas'])) ? 1 : 0;
        $empresa->modulo_crm = ($empresa->activa === 1 && isset($data['modulo_crm'])) ? 1 : 0;
        $empresa->crm_modulo_notas = ($empresa->modulo_crm === 1 && isset($data['crm_modulo_notas'])) ? 1 : 0;
        $empresa->tiendas_modulo_rxn_live = ($empresa->modulo_tiendas === 1 && isset($data['tiendas_modulo_rxn_live'])) ? 1 : 0;
        $empresa->crm_modulo_rxn_live = ($empresa->modulo_crm === 1 && isset($data['crm_modulo_rxn_live'])) ? 1 : 0;
        $empresa->crm_modulo_llamadas = ($empresa->modulo_crm === 1 && isset($data['crm_modulo_llamadas'])) ? 1 : 0;
        $empresa->crm_modulo_monitoreo = ($empresa->modulo_crm === 1 && isset($data['crm_modulo_monitoreo'])) ? 1 : 0;

        $this->repository->save($empresa);
    }

    public function copy(int $id): void
    {
        $original = $this->repository->findById($id);
        if (!$original) {
            throw new InvalidArgumentException('Empresa no encontrada.');
        }

        $empresa = new Empresa();
        $empresa->codigo = $original->codigo . '-COPIA';
        $empresa->nombre = $original->nombre . ' (Copia)';
        $empresa->razon_social = $original->razon_social;
        $empresa->cuit = $original->cuit;
        $empresa->slug = $this->generateUniqueSlug($empresa->nombre);
        $empresa->activa = 0;
        $empresa->modulo_tiendas = 0;
        $empresa->tiendas_modulo_notas = 0;
        $empresa->modulo_crm = 0;
        $empresa->crm_modulo_notas = 0;
        $empresa->modulo_rxn_live = 0;
        $empresa->crm_modulo_llamadas = 0;
        $empresa->crm_modulo_monitoreo = 0;

        $this->repository->save($empresa);
    }

    public function bulkDelete(array $ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            try {
                $this->repository->deleteById($id);
                $count++;
            } catch (\Exception $e) {
                // Ignore failure on single item
            }
        }
        return $count;
    }

    public function restore(int $id): void
    {
        $this->repository->restoreById($id);
    }

    public function bulkRestore(array $ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            try {
                $this->repository->restoreById($id);
                $count++;
            } catch (\Exception $e) {}
        }
        return $count;
    }

    public function forceDelete(int $id): void
    {
        // Add safety check: cannot hard delete enterprise 1
        if ($id === 1) {
            throw new \RuntimeException('No se puede eliminar la empresa principal (ID 1).');
        }
        $this->repository->forceDeleteById($id);
    }

    public function bulkForceDelete(array $ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            try {
                $this->forceDelete($id);
                $count++;
            } catch (\Exception $e) {}
        }
        return $count;
    }

    public function update(int $id, array $data): void
    {
        $empresa = $this->repository->findById($id);
        if (!$empresa) {
            throw new InvalidArgumentException('Empresa no encontrada.');
        }

        if (empty($data['codigo']) || empty($data['nombre'])) {
            throw new InvalidArgumentException('El código y el nombre son obligatorios.');
        }

        if ($this->repository->findByCodigo(trim($data['codigo']), $id)) {
            throw new InvalidArgumentException('El código ya está en uso por otra empresa.');
        }

        $empresa->codigo = trim($data['codigo']);
        $empresa->nombre = trim($data['nombre']);
        if (array_key_exists('titulo_pestana', $data)) {
            $empresa->titulo_pestana = $data['titulo_pestana'] !== '' ? trim((string) $data['titulo_pestana']) : null;
        }
        if (array_key_exists('razon_social', $data)) {
            $empresa->razon_social = $data['razon_social'] !== '' ? trim((string) $data['razon_social']) : null;
        }
        if (array_key_exists('cuit', $data)) {
            $empresa->cuit = $data['cuit'] !== '' ? trim((string) $data['cuit']) : null;
        }
        $empresa->slug = $this->generateUniqueSlug($empresa->nombre, $empresa->id);
        $empresa->activa = isset($data['activa']) ? 1 : 0;
        $empresa->modulo_tiendas = ($empresa->activa === 1 && isset($data['modulo_tiendas'])) ? 1 : 0;
        $empresa->tiendas_modulo_notas = ($empresa->modulo_tiendas === 1 && isset($data['tiendas_modulo_notas'])) ? 1 : 0;
        $empresa->modulo_crm = ($empresa->activa === 1 && isset($data['modulo_crm'])) ? 1 : 0;
        $empresa->crm_modulo_notas = ($empresa->modulo_crm === 1 && isset($data['crm_modulo_notas'])) ? 1 : 0;
        $empresa->tiendas_modulo_rxn_live = ($empresa->modulo_tiendas === 1 && isset($data['tiendas_modulo_rxn_live'])) ? 1 : 0;
        $empresa->crm_modulo_rxn_live = ($empresa->modulo_crm === 1 && isset($data['crm_modulo_rxn_live'])) ? 1 : 0;
        $empresa->crm_modulo_llamadas = ($empresa->modulo_crm === 1 && isset($data['crm_modulo_llamadas'])) ? 1 : 0;
        $empresa->crm_modulo_monitoreo = ($empresa->modulo_crm === 1 && isset($data['crm_modulo_monitoreo'])) ? 1 : 0;

        $this->repository->update($empresa);
    }

    private function generateUniqueSlug(string $nombre, ?int $excludeId = null): string
    {
        $baseSlug = $this->slugify($nombre);
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->repository->findBySlug($candidate, $excludeId) !== null) {
            $candidate = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugify(string $value): string
    {
        $value = trim($value);
        $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if ($normalized !== false) {
            $value = $normalized;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'empresa';
    }

    private function normalizeSearchField(string $field): string
    {
        return in_array($field, self::SEARCH_FIELDS, true) ? $field : 'all';
    }

    private function normalizeSortField(string $field): string
    {
        return in_array($field, self::SORT_FIELDS, true) ? $field : 'nombre';
    }

    private function normalizeSortDirection(string $direction): string
    {
        $direction = strtolower($direction);

        return in_array($direction, self::SORT_DIRECTIONS, true) ? $direction : 'asc';
    }

    private function normalizePage(mixed $page, int $lastPage): int
    {
        $pageNumber = filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($pageNumber === false) {
            return 1;
        }

        return min($pageNumber, $lastPage);
    }
}
