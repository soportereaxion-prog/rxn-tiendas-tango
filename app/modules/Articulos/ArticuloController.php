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
}
