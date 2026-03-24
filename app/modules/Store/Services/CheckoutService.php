<?php

declare(strict_types=1);

namespace App\Modules\Store\Services;

use App\Core\Database;
use App\Modules\ClientesWeb\ClienteWebRepository;
use App\Modules\Pedidos\PedidoWebRepository;
use App\Modules\Store\Services\CartService;
use App\Modules\Tango\TangoOrderClient;
use App\Modules\Tango\Mappers\TangoOrderMapper;
use Exception;

class CheckoutService
{
    private CartService $cartService;
    private ClienteWebRepository $clienteRepo;
    private PedidoWebRepository $pedidoRepo;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
        $this->clienteRepo = new ClienteWebRepository();
        $this->pedidoRepo = new PedidoWebRepository();
    }

    /**
     * Orquesta la creación del cliente local, el pedido local y el envío a Tango.
     */
    public function processCheckout(int $empresaId, array $clienteData, string $observaciones = ''): array
    {
        $items = $this->cartService->getItems();
        $total = $this->cartService->getTotal();

        if (empty($items)) {
            throw new Exception("El carrito está vacío. No se puede procesar el checkout.");
        }

        // 1. Resolver Cliente Web Local (Reutilización o Creación)
        // Regla: priorizar documento o email
        $clienteExistente = $this->clienteRepo->findByDocumentoOrEmail(
            $empresaId, 
            $clienteData['documento'] ?? null, 
            $clienteData['email']
        );

        if ($clienteExistente) {
            $clienteWebId = (int) $clienteExistente['id'];
            $codigoTangoUsado = $clienteExistente['codigo_tango'] ?? '000000';
            // Opcionalmente actualizar datos si cambiaron (teléfono, dirección)
            $this->clienteRepo->updateIfChanged($clienteWebId, $clienteData);
        } else {
            $clienteData['empresa_id'] = $empresaId;
            $clienteData['codigo_tango'] = null; // No forzar alta ni código inventado
            $clienteWebId = $this->clienteRepo->create($clienteData);
            $codigoTangoUsado = '000000';
            $clienteExistente = array_merge(['id' => $clienteWebId, 'codigo_tango' => null], $clienteData);
        }

        // 2. Preparar datos para Pedido Local
        $cabecera = [
            'empresa_id' => $empresaId,
            'cliente_web_id' => $clienteWebId,
            'codigo_cliente_tango_usado' => $codigoTangoUsado,
            'total' => $total,
            'observaciones' => $observaciones
        ];

        // Mapear los renglones (Necesitamos traer el codigo_tango del articulo)
        $renglones = [];
        $pdo = Database::getConnection(); // Para buscar el código del artículo rápido
        $stmtArt = $pdo->prepare("SELECT codigo_articulo FROM articulos WHERE id = :id");

        foreach ($items as $item) {
            $stmtArt->execute(['id' => $item['articulo_id']]);
            $codArtObj = $stmtArt->fetchColumn();

            $renglones[] = [
                'articulo_id' => $item['articulo_id'],
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $item['precio_unitario'],
                'nombre_articulo' => $item['nombre'],
                'codigo_articulo_tango' => $codArtObj ?: ''
            ];
        }

        // 3. Crear Pedido Local Transaccional (Pendiente de Envío)
        $pedidoId = $this->pedidoRepo->createPedido($cabecera, $renglones);

        // Actualizamos cabecera con el ID generado para el Mapper
        $cabecera['id'] = $pedidoId;

        // 4. Intentar Enviar a Tango (19845)
        $tangoPayload = TangoOrderMapper::map($cabecera, $renglones, $clienteExistente);
        $tangoSuccess = false;
        
        try {
            // Inicializar Cliente Tango (Acá deberíamos inyectar credenciales como en TangoService)
            // Por simplicidad de este iteración usaremos la configuración de la empresa via Context o Query manual
            $stmtConf = $pdo->prepare("SELECT tango_connect_token, tango_connect_company_id, tango_connect_key FROM empresa_config WHERE empresa_id = :emp_id LIMIT 1");
            $stmtConf->execute(['emp_id' => $empresaId]);
            $conf = $stmtConf->fetch();

            if ($conf && $conf['tango_connect_token']) {
                $apiUrl = "https://" . str_replace('/', '-', $conf['tango_connect_key']) . ".connect.axoft.com/Api";
                $tangoClient = new TangoOrderClient(
                    $apiUrl,
                    $conf['tango_connect_token'],
                    $conf['tango_connect_company_id'],
                    $conf['tango_connect_key']
                );

                $response = $tangoClient->sendOrder($tangoPayload);
                
                // Analizar respuesta 19845. Dependiendo de Tango, status HTTP 200/201 es éxito.
                if ($response['status'] >= 200 && $response['status'] < 300) {
                    $tangoPedidoNum = $response['data']['orderNumber'] ?? 'N/A'; // Ajustar según respuesta real
                    $this->pedidoRepo->markAsSentToTango($pedidoId, $tangoPedidoNum, json_encode($tangoPayload), json_encode($response));
                    $tangoSuccess = true;
                } else {
                    $this->pedidoRepo->markAsErrorToTango($pedidoId, json_encode($tangoPayload), json_encode($response));
                }
            } else {
                $this->pedidoRepo->markAsErrorToTango($pedidoId, json_encode($tangoPayload), "Sin credenciales Tango para la empresa.");
            }
        } catch (Exception $e) {
            // Caída de red o excepcion HTTP
            $this->pedidoRepo->markAsErrorToTango($pedidoId, json_encode($tangoPayload), $e->getMessage());
        }

        // 5. Limpiar Carrito tras pedido exitoso localmente (sin importar si Tango falló, el local ya guardó la orden)
        $this->cartService->clearCart();

        return [
            'pedido_web_id' => $pedidoId,
            'tango_enviado' => $tangoSuccess,
            'total' => $total
        ];
    }
}
