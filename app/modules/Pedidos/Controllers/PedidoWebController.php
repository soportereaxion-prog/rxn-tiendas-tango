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
    private const SEARCH_FIELDS = ['all', 'id', 'cliente', 'email', 'estado'];
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
        $field  = $this->normalizeSearchField($_GET['field'] ?? 'all');
        $estado = trim($_GET['estado'] ?? '');
        $sort   = trim($_GET['sort'] ?? 'p.created_at');
        $dir    = trim($_GET['dir'] ?? 'DESC');
        $status = trim($_GET['status'] ?? 'activos');
        $advancedFilters = $this->handleCrudFilters('pedidos_web');

        $totalItems = $this->pedidoRepo->countAll($empresaId, $search, $field, $estado, $status, $advancedFilters);
        $totalPages = (int)ceil($totalItems / $limit);
        $pedidos = $this->pedidoRepo->findAllPaginated($empresaId, $page, $limit, $search, $field, $estado, $sort, $dir, $status, $advancedFilters);

        View::render('app/modules/Pedidos/views/index.php', [
            'pedidos'    => $pedidos,
            'page'       => $page,
            'limit'      => $limit,
            'search'     => $search,
            'field'      => $field,
            'estado'     => $estado,
            'sort'       => $sort,
            'dir'        => $dir,
            'status'     => $status,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'filters'    => $advancedFilters
        ]);
    }

    public function suggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');
        $empresaId = Context::getEmpresaId();
        $search = trim((string) ($_GET['q'] ?? ''));
        $field = $this->normalizeSearchField($_GET['field'] ?? 'all');
        $estado = trim((string) ($_GET['estado'] ?? ''));

        if (mb_strlen($search) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $rows = $this->pedidoRepo->findSuggestions($empresaId, $search, $field, $estado, 3);
        $data = array_map(function (array $row) use ($field): array {
            $cliente = trim(((string) ($row['cliente_nombre'] ?? '')) . ' ' . ((string) ($row['cliente_apellido'] ?? '')));
            $email = trim((string) ($row['cliente_email'] ?? ''));
            $estadoPedido = trim((string) ($row['estado_tango'] ?? ''));
            $pedidoId = (string) ((int) ($row['id'] ?? 0));
            $value = match ($field) {
                'cliente' => $cliente,
                'email' => $email,
                'estado' => $estadoPedido,
                default => $pedidoId,
            };

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => 'Pedido #' . $pedidoId,
                'value' => $value !== '' ? $value : $pedidoId,
                'caption' => trim(($cliente !== '' ? $cliente : 'Sin cliente') . ' | ' . ($email !== '' ? $email : 'Sin email') . ' | ' . ($estadoPedido !== '' ? $estadoPedido : 'sin estado')),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    private function normalizeSearchField(string $field): string
    {
        return in_array($field, self::SEARCH_FIELDS, true) ? $field : 'all';
    }

    public function show(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        $pedidoId = (int)$id;

        $pedido = $this->pedidoRepo->findByIdWithDetails($pedidoId, $empresaId);

        if (!$pedido) {
            \App\Core\Flash::set('El pedido no existe o no pertenece a tu empresa.', 'danger');
            header("Location: /mi-empresa/pedidos");
            exit;
        }

        $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
        $cleanMessage = null;

        if (!$isGlobalAdmin && $pedido['respuesta_tango']) {
            $respArray = json_decode((string)$pedido['respuesta_tango'], true);
            $dataBody = $respArray['data'] ?? $respArray;

            $msgs = [];
            if (is_array($dataBody)) {
                if (isset($dataBody[0]) && is_array($dataBody[0])) {
                    foreach ($dataBody as $item) {
                        if (isset($item['description'])) {
                            $msgs[] = $item['description'];
                        } elseif (isset($item['message'])) {
                            $msgs[] = $item['message'];
                        }
                    }
                }
                
                if (empty($msgs)) {
                    if (isset($dataBody['messages']) && is_array($dataBody['messages'])) {
                        foreach ($dataBody['messages'] as $m) {
                            $msgs[] = $m['description'] ?? $m['message'] ?? (is_string($m) ? $m : 'Error desconocido de integración');
                        }
                    } elseif (isset($dataBody['Message']) && is_string($dataBody['Message'])) {
                        $msgs[] = $dataBody['Message'];
                    }
                }
            }

            $cleanMessage = !empty($msgs) ? implode(" | ", $msgs) : "Denegado por reglas de negocio del ERP.";
        }

        View::render('app/modules/Pedidos/views/show.php', [
            'pedido' => $pedido,
            'isGlobalAdmin' => $isGlobalAdmin,
            'cleanMessage' => $cleanMessage
        ]);
    }

    public function reprocesar(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        $pedidoId = (int)$id;
        $result = $this->sendPedidoToTango($pedidoId, $empresaId);
        \App\Core\Flash::set($result['message'], $result['type']);
        header("Location: /mi-empresa/pedidos/{$pedidoId}");
        exit;
    }

    public function reprocesarSeleccionados(): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        $ids = $_POST['ids'] ?? $_POST['selected_ids'] ?? [];
        $ids = is_array($ids) ? $ids : [];
        $ids = $this->pedidoRepo->findIdsByEmpresaAndList($empresaId, $ids);

        if ($ids === []) {
            \App\Core\Flash::set('No hay pedidos seleccionados para reenviar.', 'danger');
            header('Location: /mi-empresa/pedidos');
            exit;
        }

        $ok = 0;
        $error = 0;

        foreach ($ids as $pedidoId) {
            $result = $this->sendPedidoToTango($pedidoId, $empresaId);
            if ($result['ok']) {
                $ok++;
            } else {
                $error++;
            }
        }

        \App\Core\Flash::set("Reenvio masivo finalizado. OK: {$ok}; Errores: {$error}.", $error > 0 ? 'warning' : 'success');
        header('Location: /mi-empresa/pedidos');
        exit;
    }

    public function reprocesarPendientes(): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        $ids = $this->pedidoRepo->findPendingIds($empresaId);

        if ($ids === []) {
            \App\Core\Flash::set('No hay pedidos pendientes para reenviar.', 'warning');
            header('Location: /mi-empresa/pedidos');
            exit;
        }

        $ok = 0;
        $error = 0;

        foreach ($ids as $pedidoId) {
            $result = $this->sendPedidoToTango($pedidoId, $empresaId);
            if ($result['ok']) {
                $ok++;
            } else {
                $error++;
            }
        }

        \App\Core\Flash::set("Reenvio de pendientes finalizado. OK: {$ok}; Errores: {$error}.", $error > 0 ? 'warning' : 'success');
        header('Location: /mi-empresa/pedidos');
        exit;
    }

    public function eliminarMasivo(): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $this->pedidoRepo->softDeleteBulk(array_map('intval', $ids), $empresaId);
            \App\Core\Flash::set('Pedidos seleccionados movidos a la papelera.', 'success');
        }
        header("Location: /mi-empresa/pedidos");
        exit;
    }

    public function restoreMasivo(): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $this->pedidoRepo->restoreBulk(array_map('intval', $ids), $empresaId);
            \App\Core\Flash::set('Pedidos seleccionados restaurados.', 'success');
        }
        header("Location: /mi-empresa/pedidos?status=papelera");
        exit;
    }

    public function forceDeleteMasivo(): void
    {
        AuthService::requireLogin();
        $empresaId = Context::getEmpresaId();
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $this->pedidoRepo->forceDeleteBulk(array_map('intval', $ids), $empresaId);
            \App\Core\Flash::set('Pedidos seleccionados eliminados permanentemente.', 'success');
        }
        header("Location: /mi-empresa/pedidos?status=papelera");
        exit;
    }

    public function eliminar(string $id): void
    {
        AuthService::requireLogin();
        $this->pedidoRepo->softDelete((int)$id, Context::getEmpresaId());
        \App\Core\Flash::set('Pedido movido a la papelera.', 'success');
        header("Location: /mi-empresa/pedidos");
        exit;
    }

    public function restore(string $id): void
    {
        AuthService::requireLogin();
        $this->pedidoRepo->restore((int)$id, Context::getEmpresaId());
        \App\Core\Flash::set('Pedido restaurado.', 'success');
        header("Location: /mi-empresa/pedidos?status=papelera");
        exit;
    }

    public function forceDelete(string $id): void
    {
        AuthService::requireLogin();
        $this->pedidoRepo->forceDelete((int)$id, Context::getEmpresaId());
        \App\Core\Flash::set('Pedido eliminado permanentemente.', 'success');
        header("Location: /mi-empresa/pedidos?status=papelera");
        exit;
    }

    private function sendPedidoToTango(int $pedidoId, int $empresaId): array
    {
        $pedido = $this->pedidoRepo->findByIdWithDetails($pedidoId, $empresaId);

        if (!$pedido) {
            return ['ok' => false, 'type' => 'danger', 'message' => 'Pedido no procesable.'];
        }

        if (empty($pedido['id_gva14_tango'])) {
            return ['ok' => false, 'type' => 'danger', 'message' => 'El cliente no tiene su vínculo comercial con Tango resuelto.'];
        }

        $pdo = \App\Core\Database::getConnection();
        $tangoPayload = [];

        try {
            $stmtConf = $pdo->prepare("SELECT tango_connect_token, tango_connect_company_id, tango_connect_key FROM empresa_config WHERE empresa_id = :emp_id LIMIT 1");
            $stmtConf->execute(['emp_id' => $empresaId]);
            $conf = $stmtConf->fetch();

            if (!$conf || empty($conf['tango_connect_token'])) {
                return ['ok' => false, 'type' => 'danger', 'message' => 'Sin credenciales Tango.'];
            }

            $tangoKeyParsed = str_replace('/', '-', (string) $conf['tango_connect_key']);
            $apiUrl = rtrim(sprintf('https://%s.connect.axoft.com/Api', $tangoKeyParsed), '/');
            $tangoClient = new \App\Modules\Tango\TangoOrderClient(
                $apiUrl,
                (string) $conf['tango_connect_token'],
                (string) $conf['tango_connect_company_id']
            );

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
                'direccion' => $pedido['direccion'],
            ];

            $tangoPerfilPedidoId = null;
            // Clave de sesión correcta es `user_id` (AuthService::login la guarda así). Ver hotfix 1.16.3.
            $activeUserId = $_SESSION['user_id'] ?? null;
            if ($activeUserId) {
                $stmtUsr = $pdo->prepare("SELECT tango_perfil_pedido_id FROM usuarios WHERE id = :uid");
                $stmtUsr->execute(['uid' => $activeUserId]);
                $usrP = $stmtUsr->fetchColumn();
                if ($usrP) {
                    $tangoPerfilPedidoId = (int) $usrP;
                }
            }

            $cabecera = [
                'id' => $pedidoId,
                'empresa_id' => $empresaId,
                'created_at' => $pedido['pedido_fecha'],
                'observaciones' => $pedido['pedido_observaciones'],
                'total' => $pedido['total'],
                'tango_perfil_pedido_id' => $tangoPerfilPedidoId,
            ];

            $renglonesMapped = [];
            foreach ($pedido['renglones'] as $r) {
                $stmtArt = $pdo->prepare('SELECT codigo_externo FROM articulos WHERE id = :id');
                $stmtArt->execute(['id' => $r['articulo_id']]);
                $codigoArticulo = (string) ($stmtArt->fetchColumn() ?: '');

                if ($codigoArticulo === '') {
                    throw new \Exception('Un renglón del pedido no tiene código de artículo enlazado preventivamente.');
                }

                $idSta11 = $tangoClient->getArticleIdByCode($codigoArticulo);
                if (!$idSta11) {
                    throw new \Exception("El artículo codificado como '{$codigoArticulo}' no existe como ID_STA11 válido en la BD de Tango Connect.");
                }

                $renglonesMapped[] = [
                    'id_sta11_tango' => $idSta11,
                    'codigo_articulo' => $codigoArticulo,
                    'cantidad' => $r['cantidad'],
                    'precio_unitario' => $r['precio_unitario'],
                ];
            }

            $tangoPayload = \App\Modules\Tango\Mappers\TangoOrderMapper::map($cabecera, $renglonesMapped, $clienteWeb);
            $response = $tangoClient->sendOrder($tangoPayload);

            if ($response['status'] >= 200 && $response['status'] < 300) {
                $tangoPedidoNum = $response['data']['orderNumber'] ?? 'N/A';
                $this->pedidoRepo->markAsSentToTango($pedidoId, $tangoPedidoNum, json_encode($tangoPayload, JSON_UNESCAPED_UNICODE), json_encode($response, JSON_UNESCAPED_UNICODE));

                return ['ok' => true, 'type' => 'success', 'message' => 'Pedido enviado a Tango correctamente.'];
            }

            $errorText = is_array($response['data'] ?? null) ? json_encode($response['data'], JSON_UNESCAPED_UNICODE) : 'HTTP Error ' . $response['status'];
            $this->pedidoRepo->markAsErrorToTango($pedidoId, json_encode($tangoPayload, JSON_UNESCAPED_UNICODE), $errorText . ' | RAW RESP: ' . json_encode($response, JSON_UNESCAPED_UNICODE));

            return ['ok' => false, 'type' => 'danger', 'message' => 'Error al enviar a Tango: Revisar logs en el detalle.'];
        } catch (\Exception $e) {
            $this->pedidoRepo->markAsErrorToTango($pedidoId, json_encode($tangoPayload, JSON_UNESCAPED_UNICODE), $e->getMessage());

            return ['ok' => false, 'type' => 'danger', 'message' => 'Fallo de red o validación (' . $e->getMessage() . ').'];
        }
    }
}
