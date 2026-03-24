<?php
declare(strict_types=1);
namespace App\Modules\Articulos;

use App\Core\Controller;
use App\Core\View;
use App\Core\Context;
use App\Modules\Auth\AuthService;

class ArticuloController extends Controller
{
    private ArticuloRepository $repository;

    public function __construct()
    {
        $this->repository = new ArticuloRepository();
    }

    public function index(): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        if (!in_array($limit, [25, 50, 100])) {
            $limit = 50;
        }

        $sort = $_GET['sort'] ?? 'nombre';
        $dir = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $totalItems = $this->repository->countAll($empresaId, $search);
        $totalPages = ceil($totalItems / $limit) ?: 1;
        $articulos = $this->repository->findAllPaginated($empresaId, $page, $limit, $search, $sort, $dir);
        
        View::render('app/modules/Articulos/views/index.php', [
            'articulos' => $articulos,
            'search' => $search,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'sort' => $sort,
            'dir' => $dir
        ]);
    }
    
    public function purgar(): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->repository->truncateArticulos($empresaId);
            \App\Core\FileCache::clearPrefix("catalogo_empresa_{$empresaId}");
            \App\Core\Flash::set('success', 'Base de datos de Artículos purgada por completo.');
        }
        
        header('Location: /rxnTiendasIA/public/mi-empresa/articulos');
        exit;
    }

    public function eliminarMasivo(): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (!empty($ids) && is_array($ids)) {
                $ids = array_map('intval', $ids);
                $this->repository->deleteByIds($ids, $empresaId);
                \App\Core\FileCache::clearPrefix("catalogo_empresa_{$empresaId}");
            }
        }
        
        header('Location: /rxnTiendasIA/public/mi-empresa/articulos');
        exit;
    }

    public function editar(): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        $id = (int)($_GET['id'] ?? 0);
        
        $articulo = $this->repository->findById($id, $empresaId);
        if (!$articulo) {
            header('Location: /rxnTiendasIA/public/mi-empresa/articulos');
            exit;
        }
        
        $imagenes = $this->repository->obtenerImagenesArticulo($empresaId, $id);
        
        View::render('app/modules/Articulos/views/form.php', [
            'articulo' => $articulo,
            'imagenes' => $imagenes
        ]);
    }

    public function actualizar(): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        $id = (int)($_GET['id'] ?? 0);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $articulo = $this->repository->findById($id, $empresaId);
            if ($articulo) {
                // Not editing codigo_externo (SKU represents remote Sync ID anchor)
                $articulo->nombre = trim($_POST['nombre'] ?? '');
                $articulo->descripcion = trim($_POST['descripcion'] ?? '') ?: null;
                $precioStr = $_POST['precio'] ?? '';
                $articulo->precio = $precioStr !== '' ? (float)$precioStr : null;
                
                $precioL1Str = $_POST['precio_lista_1'] ?? '';
                $articulo->precio_lista_1 = $precioL1Str !== '' ? (float)$precioL1Str : null;

                $precioL2Str = $_POST['precio_lista_2'] ?? '';
                $articulo->precio_lista_2 = $precioL2Str !== '' ? (float)$precioL2Str : null;

                $stockStr = $_POST['stock_actual'] ?? '';
                $articulo->stock_actual = $stockStr !== '' ? (float)$stockStr : null;

                $articulo->activo = isset($_POST['activo']) ? 1 : 0;
                
                $this->repository->update($articulo);
                
                // Acciones de imágenes existentes
                if (isset($_POST['delete_img'])) {
                    $imgId = (int)$_POST['delete_img'];
                    $this->repository->eliminarImagen($empresaId, $id, $imgId);
                }

                if (isset($_POST['set_main_img'])) {
                    $imgId = (int)$_POST['set_main_img'];
                    $this->repository->marcarImagenPrincipal($empresaId, $id, $imgId);
                }

                // Subida múltiple (Fase 5 - Máximo 5 imágenes)
                if (isset($_FILES['imagenes']) && is_array($_FILES['imagenes']['name'])) {
                    $currentTotal = count($this->repository->obtenerImagenesArticulo($empresaId, $id));
                    $remaining = 5 - $currentTotal;
                    
                    if ($remaining > 0) {
                        $filesCount = count($_FILES['imagenes']['name']);
                        
                        for ($i = 0; $i < $filesCount; $i++) {
                            if ($remaining <= 0) break; 
                            
                            if ($_FILES['imagenes']['error'][$i] === UPLOAD_ERR_OK) {
                                $tmpName = $_FILES['imagenes']['tmp_name'][$i];
                                $name = $_FILES['imagenes']['name'][$i];
                                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                                
                                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                                    $dirUploads = __DIR__ . '/../../../public/uploads/empresas/' . $empresaId . '/productos/' . $id;
                                    if (!is_dir($dirUploads)) {
                                        mkdir($dirUploads, 0777, true);
                                    }
                                    
                                    $filename = 'emp_' . $empresaId . '_art_' . $id . '_' . time() . '_' . $i . '.' . $ext;
                                    $rutaAbsoluta = $dirUploads . '/' . $filename;
                                    
                                    if (move_uploaded_file($tmpName, $rutaAbsoluta)) {
                                        $rutaRelativa = '/uploads/empresas/' . $empresaId . '/productos/' . $id . '/' . $filename;
                                        
                                        $isPrincipal = ($currentTotal === 0) ? 1 : 0;
                                        $this->repository->guardarImagen($empresaId, $id, $rutaRelativa, $isPrincipal);
                                        $currentTotal++;
                                        $remaining--;
                                    }
                                }
                            }
                        }
                    }
                }

                \App\Core\FileCache::clearPrefix("catalogo_empresa_{$empresaId}");
            }
        }
        
        header('Location: /rxnTiendasIA/public/mi-empresa/articulos');
        exit;
    }
}
