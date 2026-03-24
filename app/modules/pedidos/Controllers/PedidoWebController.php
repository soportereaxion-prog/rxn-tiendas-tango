<?php
declare(strict_types=1);

namespace App\Modules\Pedidos\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Core\Context;
use App\Modules\Auth\AuthService;
use App\Modules\Pedidos\PedidoWebRepository;

class PedidoWebController extends Controller
{
    private PedidoWebRepository $pedidoRepo;

    public function __construct()
    {
        // El constructor no maneja dependencias directas en PHP si no están inyectables
        $this->pedidoRepo = new PedidoWebRepository();
    }

    public function index(): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = max(10, min(100, (int)($_GET['limit'] ?? 25)));
        $search = trim($_GET['search'] ?? '');
        $estado = trim($_GET['estado'] ?? '');
        $sort   = trim($_GET['sort'] ?? 'p.created_at');
        $dir    = trim($_GET['dir'] ?? 'DESC');

        $totalItems = $this->pedidoRepo->countAll($empresaId, $search, $estado);
        $totalPages = (int)ceil($totalItems / $limit);
        $pedidos = $this->pedidoRepo->findAllPaginated($empresaId, $page, $limit, $search, $estado, $sort, $dir);

        View::render('app/modules/Pedidos/views/index.php', [
            'pedidos'    => $pedidos,
            'page'       => $page,
            'limit'      => $limit,
            'search'     => $search,
            'estado'     => $estado,
            'sort'       => $sort,
            'dir'        => $dir,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems
        ]);
    }

    public function show(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        $pedidoId = (int)$id;

        $pedido = $this->pedidoRepo->findByIdWithDetails($pedidoId, $empresaId);

        if (!$pedido) {
            \App\Core\Flash::set('El pedido no existe o no pertenece a tu empresa.', 'danger');
            header("Location: /rxnTiendasIA/public/mi-empresa/pedidos");
            exit;
        }

        View::render('app/modules/Pedidos/views/show.php', [
            'pedido' => $pedido
        ]);
    }
}
