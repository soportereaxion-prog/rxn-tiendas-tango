<?php

declare(strict_types=1);

namespace App\Modules\Store\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Store\Services\StoreResolver;
use App\Modules\Store\Context\PublicStoreContext;
use App\Modules\Store\Context\ClienteWebContext;
use App\Modules\Pedidos\PedidoWebRepository;

class MisPedidosController extends Controller
{
    private PedidoWebRepository $pedidoRepo;

    public function __construct()
    {
        $this->pedidoRepo = new PedidoWebRepository();
    }

    private function requireAuthStore(string $slug): void
    {
        if (!StoreResolver::resolveEmpresaPublica($slug)) {
            header("Location: /public-error");
            exit;
        }

        $empresaId = PublicStoreContext::getEmpresaId();
        if (!ClienteWebContext::isLoggedIn($empresaId)) {
            header("Location: /{$slug}/login");
            exit;
        }
    }

    public function index(string $slug): void
    {
        $this->requireAuthStore($slug);
        $empresaId = PublicStoreContext::getEmpresaId();
        $clienteId = ClienteWebContext::getClienteId();

        // Para listar pedidos de un cliente específico temporalmente modificaremos el array devuelto o buscaremos con PDO custom
        // Como no queremos reescribir todo PedidoWebRepository de Admin, inyectamos una consulta simplificada acá o usamos un helper.
        $pdo = \App\Core\Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM pedidos_web WHERE empresa_id = :emp_id AND cliente_web_id = :cli_id ORDER BY created_at DESC");
        $stmt->execute(['emp_id' => $empresaId, 'cli_id' => $clienteId]);
        $pedidos = $stmt->fetchAll();

        View::render('app/modules/Store/views/mis_pedidos/index.php', [
            'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
            'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
            'pedidos'        => $pedidos
        ]);
    }

    public function show(string $slug, int $id): void
    {
        $this->requireAuthStore($slug);
        $empresaId = PublicStoreContext::getEmpresaId();
        $clienteId = ClienteWebContext::getClienteId();

        $pedido = $this->pedidoRepo->findByIdWithDetails($id, $empresaId);

        if (!$pedido || (int)$pedido['cliente_web_id'] !== $clienteId) {
            header("Location: /{$slug}/mis-pedidos");
            exit;
        }

        View::render('app/modules/Store/views/mis_pedidos/show.php', [
            'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
            'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
            'pedido'         => $pedido
        ]);
    }
}
