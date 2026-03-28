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

    public function findAll(array $filters = []): array
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $field = $this->normalizeSearchField($filters['field'] ?? 'all');
        $sort = $this->normalizeSortField($filters['sort'] ?? 'nombre');
        $dir = $this->normalizeSortDirection($filters['dir'] ?? 'asc');
        $total = $this->repository->countAll();
        $filteredTotal = $this->repository->countFiltered($search, $field);
        $lastPage = max(1, (int) ceil($filteredTotal / self::PER_PAGE));
        $page = $this->normalizePage($filters['page'] ?? 1, $lastPage);
        $offset = ($page - 1) * self::PER_PAGE;

        return [
            'items' => $this->repository->findAll($search, $field, $sort, $dir, self::PER_PAGE, $offset),
            'filters' => [
                'search' => $search,
                'field' => $field,
                'sort' => $sort,
                'dir' => $dir,
                'page' => $page,
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

    public function findById(int $id): ?Empresa
    {
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
        $empresa->razon_social = !empty($data['razon_social']) ? trim($data['razon_social']) : null;
        $empresa->cuit = !empty($data['cuit']) ? trim($data['cuit']) : null;
        $empresa->slug = $this->generateUniqueSlug($empresa->nombre);
        $empresa->activa = isset($data['activa']) ? 1 : 0;
        $empresa->modulo_tiendas = ($empresa->activa === 1 && isset($data['modulo_tiendas'])) ? 1 : 0;
        $empresa->modulo_crm = ($empresa->activa === 1 && isset($data['modulo_crm'])) ? 1 : 0;

        $this->repository->save($empresa);
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
        if (array_key_exists('razon_social', $data)) {
            $empresa->razon_social = $data['razon_social'] !== '' ? trim((string) $data['razon_social']) : null;
        }
        if (array_key_exists('cuit', $data)) {
            $empresa->cuit = $data['cuit'] !== '' ? trim((string) $data['cuit']) : null;
        }
        $empresa->slug = $this->generateUniqueSlug($empresa->nombre, $empresa->id);
        $empresa->activa = isset($data['activa']) ? 1 : 0;
        $empresa->modulo_tiendas = ($empresa->activa === 1 && isset($data['modulo_tiendas'])) ? 1 : 0;
        $empresa->modulo_crm = ($empresa->activa === 1 && isset($data['modulo_crm'])) ? 1 : 0;

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
