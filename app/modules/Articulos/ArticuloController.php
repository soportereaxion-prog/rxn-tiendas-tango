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
        
        $articulos = $this->repository->findAll($empresaId);
        
        View::render('app/modules/Articulos/views/index.php', [
            'articulos' => $articulos
        ]);
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
        
        View::render('app/modules/Articulos/views/form.php', [
            'articulo' => $articulo
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
                $articulo->activo = isset($_POST['activo']) ? 1 : 0;
                
                $this->repository->update($articulo);
            }
        }
        
        header('Location: /rxnTiendasIA/public/mi-empresa/articulos');
        exit;
    }
}
