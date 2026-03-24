<?php
declare(strict_types=1);

namespace App\Modules\Store\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Store\Services\StoreResolver;
use App\Modules\Store\Context\PublicStoreContext;
use App\Modules\Articulos\ArticuloRepository;

class StoreController extends Controller
{
    private ArticuloRepository $articuloRepo;

    public function __construct()
    {
        $this->articuloRepo = new ArticuloRepository();
    }

    /**
     * Middleware helper
     */
    private function requireValidStore(string $slug): void
    {
        if (!StoreResolver::resolveEmpresaPublica($slug)) {
            header("Location: /rxnTiendasIA/public/public-error");
            exit;
        }
    }

    public function index(string $slug): void
    {
        $this->requireValidStore($slug);

        $empresaId = PublicStoreContext::getEmpresaId();
        
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 24; // Fijo para grid layout visual iteracion 1

        $cacheKey = "catalogo_empresa_{$empresaId}_p{$page}_s" . md5($search);
        
        $cachedData = \App\Core\FileCache::get($cacheKey);
        
        if ($cachedData !== null) {
            $totalItems = $cachedData['totalItems'];
            $articulos = $cachedData['articulos'];
        } else {
            $totalItems = $this->articuloRepo->countAll($empresaId, $search);
            // El catálogo público DEBE mostrar solo ACTIVOS. Como la UI lo oculta abajo forzamos la misma query
            $articulos = $this->articuloRepo->findAllPaginated($empresaId, $page, $limit, $search, 'nombre', 'ASC');
            
            \App\Core\FileCache::set($cacheKey, [
                'totalItems' => $totalItems,
                'articulos' => $articulos
            ], 3600); // 1hr cache
        }

        $totalPages = ceil($totalItems / $limit) ?: 1;

        View::render('app/modules/Store/views/index.php', [
            'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
            'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
            'articulos'      => $articulos,
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
            header("Location: /rxnTiendasIA/public/{$slug}");
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
}
