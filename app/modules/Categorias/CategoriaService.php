<?php
declare(strict_types=1);

namespace App\Modules\Categorias;

use App\Core\Context;
use RuntimeException;

class CategoriaService
{
    private const SEARCH_FIELDS = ['all', 'nombre', 'slug', 'descripcion_corta'];
    private const SORT_FIELDS = ['nombre', 'slug', 'orden_visual', 'activa', 'visible_store'];
    private const SORT_DIRECTIONS = ['asc', 'desc'];
    private const PER_PAGE = 10;
    private const SUGGESTION_LIMIT = 3;

    private CategoriaRepository $repository;

    public function __construct()
    {
        $this->repository = new CategoriaRepository();
    }

    public function findAllForContext(array $filters = [], array $advancedFilters = []): array
    {
        $empresaId = $this->getContextId();
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $field = $this->normalizeSearchField($filters['field'] ?? 'all');
        $sort = $this->normalizeSortField($filters['sort'] ?? 'orden_visual');
        $dir = $this->normalizeSortDirection($filters['dir'] ?? 'asc');

        $status = $filters['status'] ?? 'activos';
        $onlyDeleted = $status === 'papelera';

        $total = $this->repository->countAllByEmpresaId($empresaId);
        $filteredTotal = $this->repository->countFilteredByEmpresaId($empresaId, $search, $field, $onlyDeleted, $advancedFilters);
        $lastPage = max(1, (int) ceil($filteredTotal / self::PER_PAGE));
        $page = $this->normalizePage($filters['page'] ?? 1, $lastPage);
        $offset = ($page - 1) * self::PER_PAGE;

        $items = $this->repository->findFilteredPaginatedByEmpresaId(
            $empresaId,
            $search,
            $field,
            $sort,
            $dir,
            self::PER_PAGE,
            $offset,
            $onlyDeleted,
            $advancedFilters
        );

        return [
            'items' => $items,
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

    public function findSuggestionsForContext(array $filters = []): array
    {
        $search = isset($filters['q']) ? trim((string) $filters['q']) : '';
        $field = $this->normalizeSearchField($filters['field'] ?? 'all');

        if (mb_strlen($search) < 2) {
            return [];
        }

        $rows = $this->repository->findSuggestionsByEmpresaId($this->getContextId(), $search, $field, self::SUGGESTION_LIMIT);

        return array_map(function (array $row) use ($field): array {
            $nombre = trim((string) ($row['nombre'] ?? 'Categoria'));
            $slug = trim((string) ($row['slug'] ?? ''));
            $descripcion = trim((string) ($row['descripcion_corta'] ?? ''));
            $value = match ($field) {
                'slug' => $slug,
                'descripcion_corta' => $descripcion,
                default => $nombre,
            };

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $nombre,
                'value' => $value !== '' ? $value : $nombre,
                'caption' => ($slug !== '' ? $slug : 'sin-slug') . ($descripcion !== '' ? ' | ' . $descripcion : ''),
            ];
        }, $rows);
    }

    public function getByIdForContext(int $id, bool $includeDeleted = false): Categoria
    {
        $categoria = $this->repository->findByIdAndEmpresaId($id, $this->getContextId(), $includeDeleted);
        if ($categoria === null) {
            throw new RuntimeException('La categoria solicitada no existe o no pertenece a tu empresa.');
        }

        return $categoria;
    }

    public function getSelectableForContext(bool $onlyActive = false): array
    {
        return $this->repository->findSelectableByEmpresaId($this->getContextId(), $onlyActive);
    }

    public function create(array $data, array $files = []): void
    {
        $categoria = new Categoria();
        $categoria->empresa_id = $this->getContextId();

        $this->fillCategoria($categoria, $data, $files);
        $this->repository->save($categoria);
    }

    public function update(int $id, array $data, array $files = []): void
    {
        $categoria = $this->getByIdForContext($id, true);
        $this->fillCategoria($categoria, $data, $files);
        $this->repository->save($categoria);
    }

    public function delete(array $ids): int
    {
        return $this->repository->deleteByIds($ids, $this->getContextId());
    }

    public function restore(array $ids): int
    {
        return $this->repository->restoreByIds($ids, $this->getContextId());
    }

    public function forceDelete(array $ids): int
    {
        return $this->repository->forceDeleteByIds($ids, $this->getContextId());
    }

    private function fillCategoria(Categoria $categoria, array $data, array $files): void
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre de la categoria es obligatorio.');
        }

        $categoria->nombre = $nombre;
        $categoria->slug = $this->generateUniqueSlug(
            $categoria->empresa_id,
            trim((string) ($data['slug'] ?? '')) !== '' ? (string) $data['slug'] : $nombre,
            $categoria->id
        );
        $categoria->descripcion_corta = trim((string) ($data['descripcion_corta'] ?? '')) ?: null;
        $categoria->orden_visual = max(0, (int) ($data['orden_visual'] ?? 0));
        $categoria->activa = $this->normalizeCheckbox($data, 'activa');
        $categoria->visible_store = $this->normalizeCheckbox($data, 'visible_store');
        $categoria->imagen_portada = $this->storeImageIfPresent(
            $categoria->empresa_id,
            $files['imagen_portada'] ?? null,
            $categoria->imagen_portada
        );
    }

    private function storeImageIfPresent(int $empresaId, mixed $file, ?string $currentPath): ?string
    {
        if (!is_array($file)) {
            return $currentPath;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return $currentPath;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo subir la imagen de la categoria.');
        }

        $name = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('La imagen debe estar en formato JPG, PNG o WEBP.');
        }

        $dir = BASE_PATH . '/public/uploads/empresas/' . $empresaId . '/categorias';
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo preparar la carpeta de categorias.');
        }

        $filename = 'categoria_' . $empresaId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $absolutePath = $dir . '/' . $filename;
        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !move_uploaded_file($tmpName, $absolutePath)) {
            throw new RuntimeException('No se pudo guardar la imagen de la categoria.');
        }

        if ($currentPath !== null && str_starts_with($currentPath, '/uploads/')) {
            $currentAbsolute = BASE_PATH . '/public' . $currentPath;
            if (is_file($currentAbsolute)) {
                @unlink($currentAbsolute);
            }
        }

        return '/uploads/empresas/' . $empresaId . '/categorias/' . $filename;
    }

    private function generateUniqueSlug(int $empresaId, string $source, ?int $excludeId = null): string
    {
        $baseSlug = $this->slugify($source);
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->repository->findBySlug($empresaId, $candidate, $excludeId) !== null) {
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

        return $value !== '' ? $value : 'categoria';
    }

    private function getContextId(): int
    {
        $empresaId = Context::getEmpresaId();
        if ($empresaId === null) {
            throw new RuntimeException('No hay una empresa activa en el contexto.');
        }

        return $empresaId;
    }

    private function normalizeCheckbox(array $data, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];
        return in_array($value, ['1', 'on', 1, true], true) ? 1 : 0;
    }

    private function normalizeSearchField(string $field): string
    {
        return in_array($field, self::SEARCH_FIELDS, true) ? $field : 'all';
    }

    private function normalizeSortField(string $field): string
    {
        return in_array($field, self::SORT_FIELDS, true) ? $field : 'orden_visual';
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
