<?php
declare(strict_types=1);

namespace App\Modules\Store\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Store\Services\StoreResolver;
use App\Modules\Store\Context\PublicStoreContext;
use App\Modules\Articulos\ArticuloRepository;
use App\Modules\Categorias\Categoria;
use App\Modules\Categorias\CategoriaRepository;

class StoreController extends Controller
{
    private ArticuloRepository $articuloRepo;
    private CategoriaRepository $categoriaRepo;

    public function __construct()
    {
        $this->articuloRepo = new ArticuloRepository();
        $this->categoriaRepo = new CategoriaRepository();
    }

    /**
     * Middleware helper
     */
    private function requireValidStore(string $slug): void
    {
        if (!StoreResolver::resolveEmpresaPublica($slug)) {
            header("Location: /public-error");
            exit;
        }
    }

    public function index(string $slug): void
    {
        $this->requireValidStore($slug);

        $empresaId = PublicStoreContext::getEmpresaId();
        
        $search = trim($_GET['search'] ?? '');
        $categoriaSlug = $this->normalizeCategoriaSlug($_GET['categoria'] ?? null);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 24; // Fijo para grid layout visual iteracion 1

        $categoriasCacheKey = "categorias_store_empresa_{$empresaId}";
        $categorias = \App\Core\FileCache::get($categoriasCacheKey);
        if ($categorias === null) {
            $categorias = $this->categoriaRepo->findStoreCategoriesWithCounts($empresaId);
            \App\Core\FileCache::set($categoriasCacheKey, $categorias, 3600);
        } else {
            $categorias = $this->hydrateCategorias($categorias);
        }

        $selectedCategory = null;
        foreach ($categorias as $categoria) {
            if (($categoria->slug ?? '') === $categoriaSlug) {
                $selectedCategory = $categoria;
                break;
            }
        }

        if ($categoriaSlug !== null && $selectedCategory === null) {
            $categoriaSlug = null;
        }

        $countCacheKey = "catalogo_empresa_{$empresaId}_count_s" . md5($search) . '_c' . md5((string) $categoriaSlug);
        $totalItems = \App\Core\FileCache::get($countCacheKey);

        if ($totalItems === null) {
            $totalItems = $this->articuloRepo->countPublicCatalog($empresaId, $search, $categoriaSlug);
            \App\Core\FileCache::set($countCacheKey, $totalItems, 3600);
        }

        $totalPages = ceil($totalItems / $limit) ?: 1;
        $page = min($page, $totalPages);

        $cacheKey = "catalogo_empresa_{$empresaId}_p{$page}_s" . md5($search) . '_c' . md5((string) $categoriaSlug);
        $articulos = \App\Core\FileCache::get($cacheKey);

        if ($articulos === null) {
            $articulos = $this->articuloRepo->findPublicCatalogPaginated($empresaId, $page, $limit, $search, $categoriaSlug);
            \App\Core\FileCache::set($cacheKey, $articulos, 3600);
        }

        View::render('app/modules/Store/views/index.php', [
            'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
            'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
            'articulos'      => $articulos,
            'categorias'     => $categorias,
            'selectedCategory' => $selectedCategory,
            'categoriaSlug'  => $categoriaSlug,
            'search'         => $search,
            'page'           => $page,
            'limit'          => $limit,
            'totalPages'     => $totalPages,
            'totalItems'     => $totalItems
        ]);
    }

    public function showProduct(string $slug, string $idStr): void
    {
        $this->requireValidStore($slug);

        $id = (int) $idStr;
        $empresaId = PublicStoreContext::getEmpresaId();

        $articulo = $this->articuloRepo->findById($id, $empresaId);

        if (!$articulo || !$articulo->activo) {
            header("Location: /{$slug}");
            exit;
        }

        $imagenes = $this->articuloRepo->obtenerImagenesArticulo($empresaId, $id);

        View::render('app/modules/Store/views/show.php', [
            'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
            'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
            'articulo'       => $articulo,
            'imagenes'       => $imagenes
        ]);
    }

    private function normalizeCategoriaSlug(mixed $slug): ?string
    {
        if (!is_string($slug)) {
            return null;
        }

        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]+/', '', $slug) ?? '';

        return $slug !== '' ? $slug : null;
    }

    private function hydrateCategorias(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $categorias = [];
        foreach ($items as $item) {
            if ($item instanceof Categoria) {
                $categorias[] = $item;
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $categoria = new Categoria();
            $categoria->id = isset($item['id']) ? (int) $item['id'] : null;
            $categoria->empresa_id = (int) ($item['empresa_id'] ?? 0);
            $categoria->nombre = (string) ($item['nombre'] ?? '');
            $categoria->slug = (string) ($item['slug'] ?? '');
            $categoria->descripcion_corta = $item['descripcion_corta'] ?? null;
            $categoria->imagen_portada = $item['imagen_portada'] ?? null;
            $categoria->orden_visual = (int) ($item['orden_visual'] ?? 0);
            $categoria->activa = (int) ($item['activa'] ?? 1);
            $categoria->visible_store = (int) ($item['visible_store'] ?? 1);
            $categoria->articulos_count = (int) ($item['articulos_count'] ?? 0);
            $categorias[] = $categoria;
        }

        return $categorias;
    }
}
