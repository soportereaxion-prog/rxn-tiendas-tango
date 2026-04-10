<?php
declare(strict_types=1);

namespace App\Modules\Articulos;

use App\Core\Context;
use App\Core\Controller;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\Categorias\CategoriaRepository;
use App\Shared\Services\OperationalAreaService;

class ArticuloController extends Controller
{
    private const SEARCH_FIELDS = ['all', 'id', 'codigo_externo', 'nombre', 'descripcion'];

    private CategoriaRepository $categoriaRepository;

    public function __construct()
    {
        $this->categoriaRepository = new CategoriaRepository();
    }

    public function index(): void
    {
        AuthService::requireLogin();

        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int) Context::getEmpresaId();

        $search = trim($_GET['search'] ?? '');
        $field = $this->normalizeSearchField($_GET['field'] ?? 'all');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        $categoriaId = ($ui['showCategories'] ?? false) ? $this->normalizeCategoriaId($_GET['categoria_id'] ?? null) : null;
        if (!in_array($limit, [25, 50, 100], true)) {
            $limit = 50;
        }

        $sort = $_GET['sort'] ?? 'nombre';
        $dir = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $categorias = ($ui['showCategories'] ?? false)
            ? $this->categoriaRepository->findSelectableByEmpresaId($empresaId)
            : [];

        $status = $_GET['status'] ?? 'activos';
        $onlyDeleted = $status === 'papelera';

        $advancedFilters = $this->handleCrudFilters($area === 'crm' ? 'crm_articulos' : 'articulos');

        $totalItems = $repository->countAll($empresaId, $search, $field, $categoriaId, $onlyDeleted, $advancedFilters);
        $totalPages = ceil($totalItems / $limit) ?: 1;
        $page = min($page, $totalPages);
        $articulos = $repository->findAllPaginated($empresaId, $page, $limit, $search, $field, $sort, $dir, $categoriaId, $onlyDeleted, $advancedFilters);

        View::render('app/modules/Articulos/views/index.php', array_merge($ui, [
            'articulos' => $articulos,
            'categorias' => $categorias,
            'search' => $search,
            'field' => $field,
            'categoriaId' => $categoriaId,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'sort' => $sort,
            'dir' => $dir,
            'status' => $status,
        ]));
    }

    public function suggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $repository = $this->resolveRepository($this->resolveArea());
        $empresaId = (int) Context::getEmpresaId();
        $search = trim((string) ($_GET['q'] ?? ''));
        $field = $this->normalizeSearchField($_GET['field'] ?? 'all');

        if (mb_strlen($search) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $rows = $repository->findSuggestions($empresaId, $search, $field, 3);
        $data = array_map(function (array $row) use ($field): array {
            $nombre = trim((string) ($row['nombre'] ?? 'Articulo'));
            $codigo = trim((string) ($row['codigo_externo'] ?? ''));
            $descripcion = trim((string) ($row['descripcion'] ?? ''));
            $value = match ($field) {
                'id' => (string) ((int) ($row['id'] ?? 0)),
                'codigo_externo' => $codigo,
                'descripcion' => $descripcion,
                default => $nombre,
            };

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $nombre,
                'value' => $value !== '' ? $value : $nombre,
                'caption' => '#' . (int) ($row['id'] ?? 0) . ' | ' . ($codigo !== '' ? $codigo : 'Sin SKU'),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    public function purgar(): void
    {
        AuthService::requireLogin();

        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int) Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $repository->truncateArticulos($empresaId);
            $this->clearEnvironmentCaches($area, $empresaId);
            \App\Core\Flash::set('success', 'Base de datos de articulos purgada por completo.');
        }

        $this->redirectTo($ui['basePath']);
    }

    public function eliminarMasivo(): void
    {
        AuthService::requireLogin();

        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int) Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (!empty($ids) && is_array($ids)) {
                $ids = array_map('intval', $ids);
                $repository->deleteByIds($ids, $empresaId);
                $this->clearEnvironmentCaches($area, $empresaId);
                \App\Core\Flash::set('success', count($ids) . ' artículos enviados a la papelera.');
            }
        }

        $this->redirectTo($ui['basePath']);
    }

    public function copy(string $id): void
    {
        AuthService::requireLogin();
        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int) Context::getEmpresaId();
        $idInt = (int) $id;

        try {
            if ($idInt > 0) {
                // Not supported yet. Can implement repository copy similar to Clientes.
                \App\Core\Flash::set('danger', 'La funcionalidad de copiar artículos aún no está implementada.');
            }
        } catch (\Exception $e) {
            \App\Core\Flash::set('danger', $e->getMessage());
        }

        $this->redirectTo($ui['basePath']);
    }

    public function eliminar(string $id): void
    {
        AuthService::requireLogin();

        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int) Context::getEmpresaId();
        $idInt = (int) $id;

        if ($idInt > 0) {
            $repository->deleteByIds([$idInt], $empresaId);
            $this->clearEnvironmentCaches($area, $empresaId);
            \App\Core\Flash::set('success', 'Artículo enviado a la papelera.');
        }

        $this->redirectTo($ui['basePath']);
    }

    public function restoreMasivo(): void
    {
        AuthService::requireLogin();

        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int) Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (!empty($ids) && is_array($ids)) {
                $ids = array_map('intval', $ids);
                $repository->restoreByIds($ids, $empresaId);
                $this->clearEnvironmentCaches($area, $empresaId);
                \App\Core\Flash::set('success', count($ids) . ' artículos restaurados.');
            }
        }

        $this->redirectTo($ui['basePath'] . '?status=papelera');
    }

    public function restore(string $id): void
    {
        AuthService::requireLogin();

        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int) Context::getEmpresaId();
        $idInt = (int) $id;

        if ($idInt > 0) {
            $repository->restoreByIds([$idInt], $empresaId);
            $this->clearEnvironmentCaches($area, $empresaId);
            \App\Core\Flash::set('success', 'Artículo restaurado.');
        }

        $this->redirectTo($ui['basePath'] . '?status=papelera');
    }

    public function forceDeleteMasivo(): void
    {
        AuthService::requireLogin();

        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int) Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (!empty($ids) && is_array($ids)) {
                $ids = array_map('intval', $ids);
                $repository->forceDeleteByIds($ids, $empresaId);
                $this->clearEnvironmentCaches($area, $empresaId);
                \App\Core\Flash::set('success', count($ids) . ' artículos eliminados definitivamente.');
            }
        }

        $this->redirectTo($ui['basePath'] . '?status=papelera');
    }

    public function forceDelete(string $id): void
    {
        AuthService::requireLogin();

        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int) Context::getEmpresaId();
        $idInt = (int) $id;

        if ($idInt > 0) {
            $repository->forceDeleteByIds([$idInt], $empresaId);
            $this->clearEnvironmentCaches($area, $empresaId);
            \App\Core\Flash::set('success', 'Artículo eliminado de forma definitiva.');
        }

        $this->redirectTo($ui['basePath'] . '?status=papelera');
    }

    public function editar(): void
    {
        AuthService::requireLogin();

        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int) Context::getEmpresaId();
        $id = (int) ($_GET['id'] ?? 0);

        $articulo = $repository->findById($id, $empresaId);
        if (!$articulo) {
            $this->redirectTo($ui['basePath']);
        }

        $imagenes = $repository->obtenerImagenesArticulo($empresaId, $id);
        $categorias = ($ui['showCategories'] ?? false)
            ? $this->categoriaRepository->findSelectableByEmpresaId($empresaId)
            : [];

        View::render('app/modules/Articulos/views/form.php', array_merge($ui, [
            'articulo' => $articulo,
            'imagenes' => $imagenes,
            'categorias' => $categorias,
        ]));
    }

    public function actualizar(): void
    {
        AuthService::requireLogin();

        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int) Context::getEmpresaId();
        $id = (int) ($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $articulo = $repository->findById($id, $empresaId);
            if ($articulo) {
                $articulo->nombre = trim($_POST['nombre'] ?? '');
                $articulo->descripcion = trim($_POST['descripcion'] ?? '') ?: null;
                $precioStr = $_POST['precio'] ?? '';
                $articulo->precio = $precioStr !== '' ? (float) $precioStr : null;

                $precioL1Str = $_POST['precio_lista_1'] ?? '';
                $articulo->precio_lista_1 = $precioL1Str !== '' ? (float) $precioL1Str : null;

                $precioL2Str = $_POST['precio_lista_2'] ?? '';
                $articulo->precio_lista_2 = $precioL2Str !== '' ? (float) $precioL2Str : null;

                $stockStr = $_POST['stock_actual'] ?? '';
                $articulo->stock_actual = $stockStr !== '' ? (float) $stockStr : null;

                $articulo->activo = isset($_POST['activo']) ? 1 : 0;
                $articulo->categoria_id = ($ui['showCategories'] ?? false)
                    ? $this->resolveCategoriaId($empresaId, $_POST['categoria_id'] ?? null)
                    : null;

                $repository->update($articulo);

                if (isset($_POST['delete_img'])) {
                    $imgId = (int) $_POST['delete_img'];
                    $repository->eliminarImagen($empresaId, $id, $imgId);
                }

                if (isset($_POST['set_main_img'])) {
                    $imgId = (int) $_POST['set_main_img'];
                    $repository->marcarImagenPrincipal($empresaId, $id, $imgId);
                }

                if (isset($_FILES['imagenes']) && is_array($_FILES['imagenes']['name'])) {
                    $currentTotal = count($repository->obtenerImagenesArticulo($empresaId, $id));
                    $remaining = 5 - $currentTotal;

                    if ($remaining > 0) {
                        $filesCount = count($_FILES['imagenes']['name']);

                        for ($i = 0; $i < $filesCount; $i++) {
                            if ($remaining <= 0) {
                                break;
                            }

                            if ($_FILES['imagenes']['error'][$i] === UPLOAD_ERR_OK) {
                                $tmpName = $_FILES['imagenes']['tmp_name'][$i];
                                $name = $_FILES['imagenes']['name'][$i];
                                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                                    $dirUploads = __DIR__ . '/../../../public/uploads/empresas/' . $empresaId . '/' . $ui['uploadSegment'] . '/' . $id;
                                    if (!is_dir($dirUploads)) {
                                        mkdir($dirUploads, 0777, true);
                                    }

                                    $filename = 'emp_' . $empresaId . '_art_' . $id . '_' . time() . '_' . $i . '.' . $ext;
                                    $rutaAbsoluta = $dirUploads . '/' . $filename;

                                    if (move_uploaded_file($tmpName, $rutaAbsoluta)) {
                                        $rutaRelativa = '/uploads/empresas/' . $empresaId . '/' . $ui['uploadSegment'] . '/' . $id . '/' . $filename;

                                        $isPrincipal = ($currentTotal === 0) ? 1 : 0;
                                        $repository->guardarImagen($empresaId, $id, $rutaRelativa, $isPrincipal);
                                        $currentTotal++;
                                        $remaining--;
                                    }
                                }
                            }
                        }
                    }
                }

                $this->clearEnvironmentCaches($area, $empresaId);
            }
        }

        $this->redirectTo($ui['basePath']);
    }

    private function resolveArea(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

        return str_contains($uri, '/mi-empresa/crm/') ? 'crm' : 'tiendas';
    }

    private function resolveRepository(string $area): ArticuloRepository
    {
        return $area === 'crm' ? ArticuloRepository::forCrm() : new ArticuloRepository();
    }

    private function buildUiContext(string $area): array
    {
        if ($area === 'crm') {
            return [
                'pageTitle' => 'Articulos CRM',
                'headerTitle' => 'Directorio de Articulos CRM',
                'headerDescription' => 'Base inicial de articulos CRM con almacenamiento separado del circuito de Tiendas.',
                'dashboardPath' => '/mi-empresa/crm/dashboard',
                'basePath' => '/mi-empresa/crm/articulos',
                'helpPath' => OperationalAreaService::helpPath(OperationalAreaService::AREA_CRM),
                'moduleNotesKey' => 'crm_articulos',
                'moduleNotesLabel' => 'Articulos CRM',
                'showCategories' => false,
                'showSyncActions' => true,
                'emptyStateTitle' => 'Todavia no hay articulos cargados en CRM.',
                'emptyStateHint' => 'Puedes sincronizar articulos, precios y stock con los parametros propios de CRM.',
                'totalBadgeLabel' => 'Total CRM',
                'editTitle' => 'Modificar Articulo CRM',
                'backLabel' => 'Volver al CRM',
                'uploadSegment' => 'crm-articulos',
                'syncTodoPath' => '/mi-empresa/crm/sync/todo',
                'syncStockPath' => '/mi-empresa/crm/sync/stock',
                'syncPreciosPath' => '/mi-empresa/crm/sync/precios',
                'syncArticulosPath' => '/mi-empresa/crm/sync/articulos',
            ];
        }

        return [
            'pageTitle' => 'Catalogo de Articulos',
            'headerTitle' => 'Directorio de Articulos',
            'headerDescription' => 'Gestion de Catalogo Web, Precios e Imagenes (Tango + RXN)',
            'dashboardPath' => '/mi-empresa/dashboard',
            'basePath' => '/mi-empresa/articulos',
            'helpPath' => OperationalAreaService::helpPath(OperationalAreaService::AREA_TIENDAS),
            'moduleNotesKey' => 'articulos',
            'moduleNotesLabel' => 'Articulos',
            'showCategories' => true,
            'showSyncActions' => true,
            'emptyStateTitle' => 'El Catalogo Maestro esta vacio todavia o no hay coincidencias.',
            'emptyStateHint' => 'Haz clic en "Sync Articulos" para inyectar datos reales.',
            'totalBadgeLabel' => 'Total en BD Local',
            'editTitle' => 'Modificar Articulo',
            'backLabel' => 'Volver al Catalogo',
            'uploadSegment' => 'productos',
            'syncTodoPath' => '/mi-empresa/sync/todo',
            'syncStockPath' => '/mi-empresa/sync/stock',
            'syncPreciosPath' => '/mi-empresa/sync/precios',
            'syncArticulosPath' => '/mi-empresa/sync/articulos',
        ];
    }

    private function clearEnvironmentCaches(string $area, int $empresaId): void
    {
        if ($area !== 'tiendas') {
            return;
        }

        \App\Core\FileCache::clearPrefix('catalogo_empresa_' . $empresaId);
        \App\Core\FileCache::clearPrefix('categorias_store_empresa_' . $empresaId);
    }

    private function redirectTo(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    private function normalizeSearchField(string $field): string
    {
        return in_array($field, self::SEARCH_FIELDS, true) ? $field : 'all';
    }

    private function normalizeCategoriaId(mixed $categoriaId): ?int
    {
        $value = filter_var($categoriaId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $value === false ? null : (int) $value;
    }

    private function resolveCategoriaId(int $empresaId, mixed $categoriaId): ?int
    {
        $normalized = $this->normalizeCategoriaId($categoriaId);
        if ($normalized === null) {
            return null;
        }

        $categoria = $this->categoriaRepository->findByIdAndEmpresaId($normalized, $empresaId);
        return $categoria ? $categoria->id : null;
    }

    /**
     * AJAX: Push de un artículo individual a Tango.
     * Disponible para CRM y Tiendas. Retorna JSON con payload de auditoría.
     */
    public function pushToTango(string $id): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $area      = $this->resolveArea();
        $empresaId = (int) Context::getEmpresaId();
        $localId   = (int) $id;

        if ($localId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de artículo inválido.']);
            exit;
        }

        set_time_limit(60);

        try {
            $configRepo  = \App\Modules\EmpresaConfig\EmpresaConfigRepository::forArea($area);
            $empresaConf = $configRepo->findByEmpresaId($empresaId);
            $syncBatch   = max(50, (int) ($empresaConf->cantidad_articulos_sync ?? 500));

            $syncService = new \App\Modules\RxnSync\RxnSyncService();
            $payload = $syncService->pushToTangoByLocalId($empresaId, $localId, 'articulo', $syncBatch);
            echo json_encode([
                'success' => true,
                'message' => 'Artículo sincronizado con Tango.',
                'payload' => $payload,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
