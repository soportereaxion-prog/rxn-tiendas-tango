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

    public function reprocesar(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        $pedidoId = (int)$id;

        $pedido = $this->pedidoRepo->findByIdWithDetails($pedidoId, $empresaId);

        if (!$pedido || $pedido['estado_tango'] === 'enviado_tango') {
            \App\Core\Flash::set('Pedido no procesable.', 'danger');
            header("Location: /rxnTiendasIA/public/mi-empresa/pedidos");
            exit;
        }

        if (empty($pedido['id_gva14_tango'])) {
            \App\Core\Flash::set('El cliente NO tiene su vínculo comercial con Tango resuelto. Por favor asigne y valide un Código Tango en Clientes Web.', 'danger');
            header("Location: /rxnTiendasIA/public/mi-empresa/pedidos/{$pedidoId}");
            exit;
        }

        // Reconstruimos la llamada a Tango. Idealmente esto está abstraído en un "TangoSyncService", pero
        // usaremos el mismo pipeline de CheckoutService temporalmente aislando la parte de API.
        
        $pdo = \App\Core\Database::getConnection();
        
        // 1. Reconstruir Arrays para el Mapper (Simular Estructura Original)
        $clienteWeb = [
            'id' => $pedido['cliente_web_id'],
            'codigo_tango' => $pedido['codigo_tango'] ?? ($pedido['codigo_cliente_tango_usado'] !== '000000' ? $pedido['codigo_cliente_tango_usado'] : null),
            'id_gva14_tango' => $pedido['id_gva14_tango'],
            'id_gva01_condicion_venta' => $pedido['id_gva01_condicion_venta'],
            'id_gva10_lista_precios' => $pedido['id_gva10_lista_precios'],
            'id_gva23_vendedor' => $pedido['id_gva23_vendedor'],
            'id_gva24_transporte' => $pedido['id_gva24_transporte'],
            'nombre' => $pedido['nombre'],
            'apellido' => $pedido['apellido'],
            'documento' => $pedido['documento'],
            'direccion' => $pedido['direccion']
        ];

        $cabecera = [
            'id' => $pedidoId,
            'empresa_id' => $empresaId,
            'created_at' => $pedido['pedido_fecha'],
            'observaciones' => $pedido['pedido_observaciones'],
            'total' => $pedido['total']
        ];

        $stmtArt = $pdo->prepare("SELECT codigo_externo FROM articulos WHERE id = :id");
        $renglonesMapped = [];
        foreach ($pedido['renglones'] as $r) {
            $stmtArt->execute(['id' => $r['articulo_id']]);
            $codArt = $stmtArt->fetchColumn();
            
            $renglonesMapped[] = [
                'codigo_articulo' => $codArt ?: '',
                'cantidad' => $r['cantidad'],
                'precio_unitario' => $r['precio_unitario']
            ];
        }

        $tangoPayload = \App\Modules\Tango\Mappers\TangoOrderMapper::map($cabecera, $renglonesMapped, $clienteWeb);

        try {
            $stmtConf = $pdo->prepare("SELECT tango_connect_token, tango_connect_company_id, tango_connect_key FROM empresa_config WHERE empresa_id = :emp_id LIMIT 1");
            $stmtConf->execute(['emp_id' => $empresaId]);
            $conf = $stmtConf->fetch();

            if ($conf && $conf['tango_connect_token']) {
                $apiUrl = "https://" . str_replace('/', '-', $conf['tango_connect_key']) . ".connect.axoft.com/Api";
                $tangoClient = new \App\Modules\Tango\TangoOrderClient(
                    $apiUrl,
                    $conf['tango_connect_token'],
                    $conf['tango_connect_company_id'],
                    $conf['tango_connect_key']
                );

                $response = $tangoClient->sendOrder($tangoPayload);
                
                if ($response['status'] >= 200 && $response['status'] < 300) {
                    $tangoPedidoNum = $response['data']['orderNumber'] ?? 'N/A';
                    $this->pedidoRepo->markAsSentToTango($pedidoId, $tangoPedidoNum, json_encode($tangoPayload), json_encode($response));
                    \App\Core\Flash::set('Pedido enviado a Tango correctamente.', 'success');
                } else {
                    $errorText = is_array($response['data'] ?? null) ? json_encode($response['data'], JSON_UNESCAPED_UNICODE) : 'HTTP Error ' . $response['status'];
                    $this->pedidoRepo->markAsErrorToTango($pedidoId, json_encode($tangoPayload), $errorText, json_encode($response));
                    \App\Core\Flash::set('Error al enviar a Tango: Revisar Logs en el detalle.', 'danger');
                }
            } else {
                \App\Core\Flash::set('Sin credenciales Tango.', 'danger');
            }
        } catch (\Exception $e) {
            $this->pedidoRepo->markAsErrorToTango($pedidoId, json_encode($tangoPayload), $e->getMessage());
            \App\Core\Flash::set('Excepción de red al enviar a Tango.', 'danger');
        }

        header("Location: /rxnTiendasIA/public/mi-empresa/pedidos/{$pedidoId}");
        exit;
    }
}
