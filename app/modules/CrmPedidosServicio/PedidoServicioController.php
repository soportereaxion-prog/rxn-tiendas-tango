<?php
declare(strict_types=1);

namespace App\Modules\CrmPedidosServicio;

use App\Core\Context;
use App\Core\Flash;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Shared\Services\OperationalAreaService;
use DateTimeImmutable;
use Exception;

class PedidoServicioController
{
    private const SEARCH_FIELDS = ['all', 'numero', 'cliente', 'solicito', 'articulo', 'clasificacion', 'estado', 'usuario'];

    private PedidoServicioRepository $repository;

    public function __construct()
    {
        $this->repository = new PedidoServicioRepository();
    }

    public function index(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 25;
        if (!in_array($limit, [25, 50, 100], true)) {
            $limit = 25;
        }

        $search = trim((string) ($_GET['search'] ?? ''));
        $field = $this->normalizeSearchField((string) ($_GET['field'] ?? 'all'));
        $estado = trim((string) ($_GET['estado'] ?? ''));
        $sort = trim((string) ($_GET['sort'] ?? 'fecha_inicio'));
        $dir = strtoupper((string) ($_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $totalItems = $this->repository->countAll($empresaId, $search, $field, $estado);
        $totalPages = max(1, (int) ceil($totalItems / $limit));
        $page = min($page, $totalPages);
        $pedidos = $this->repository->findAllPaginated($empresaId, $page, $limit, $search, $field, $estado, $sort, $dir);

        View::render('app/modules/CrmPedidosServicio/views/index.php', array_merge($this->buildUiContext(), [
            'pedidos' => $pedidos,
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'field' => $field,
            'estado' => $estado,
            'sort' => $sort,
            'dir' => $dir,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
        ]));
    }



    public function eliminar(string $id): void
    {
        AuthService::requireLogin();
        $idInt = (int) $id;

        if ($idInt > 0) {
            $this->repository->deleteByIds([$idInt], (int) Context::getEmpresaId());
            Flash::set('success', 'Pedido de servicio enviado a la papelera.');
        }

        header('Location: /mi-empresa/crm/pedidos-servicio');
        exit;
    }

    public function restore(string $id): void
    {
        AuthService::requireLogin();
        $idInt = (int) $id;

        if ($idInt > 0) {
            $this->repository->restoreByIds([$idInt], (int) Context::getEmpresaId());
            Flash::set('success', 'Pedido de servicio restaurado exitosamente.');
        }

        header('Location: /mi-empresa/crm/pedidos-servicio');
        exit;
    }

    public function forceDelete(string $id): void
    {
        AuthService::requireLogin();
        $idInt = (int) $id;

        if ($idInt > 0) {
            $this->repository->forceDeleteByIds([$idInt], (int) Context::getEmpresaId());
            Flash::set('success', 'Pedido de servicio eliminado permanentemente.');
        }

        header('Location: /mi-empresa/crm/pedidos-servicio');
        exit;
    }

    public function eliminarMasivo(): void
    {
        AuthService::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (is_array($ids) && count($ids) > 0) {
                $count = $this->repository->deleteByIds($ids, (int) Context::getEmpresaId());
                Flash::set('success', "Se han enviado $count pedidos de servicio a la papelera.");
            } else {
                Flash::set('warning', 'No se seleccionó ningún pedido.');
            }
        }

        header('Location: /mi-empresa/crm/pedidos-servicio');
        exit;
    }

    public function restoreMasivo(): void
    {
        AuthService::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (is_array($ids) && count($ids) > 0) {
                $count = $this->repository->restoreByIds($ids, (int) Context::getEmpresaId());
                Flash::set('success', "Se han restaurado $count pedidos de servicio.");
            } else {
                Flash::set('warning', 'No se seleccionó ningún pedido.');
            }
        }

        header('Location: /mi-empresa/crm/pedidos-servicio');
        exit;
    }

    public function forceDeleteMasivo(): void
    {
        AuthService::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (is_array($ids) && count($ids) > 0) {
                $count = $this->repository->forceDeleteByIds($ids, (int) Context::getEmpresaId());
                Flash::set('success', "Se han eliminado $count pedidos de servicio permanentemente.");
            } else {
                Flash::set('warning', 'No se seleccionó ningún pedido.');
            }
        }

        header('Location: /mi-empresa/crm/pedidos-servicio');
        exit;
    }

    public function suggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $search = trim((string) ($_GET['q'] ?? ''));
        $field = $this->normalizeSearchField((string) ($_GET['field'] ?? 'all'));
        $estado = trim((string) ($_GET['estado'] ?? ''));

        if (mb_strlen($search) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $rows = $this->repository->findSuggestions($empresaId, $search, $field, $estado, 3);
        $data = array_map(static function (array $row) use ($field): array {
            $numero = (string) ((int) ($row['numero'] ?? 0));
            $cliente = trim((string) ($row['cliente_nombre'] ?? ''));
            $articulo = trim((string) ($row['articulo_nombre'] ?? ''));
            $clasificacion = trim((string) ($row['clasificacion_codigo'] ?? ''));
            $estadoUi = empty($row['fecha_finalizado']) ? 'abierto' : 'finalizado';

            $value = match ($field) {
                'cliente' => $cliente,
                'solicito' => trim((string) ($row['solicito'] ?? '')),
                'articulo' => $articulo,
                'clasificacion' => $clasificacion,
                'estado' => $estadoUi,
                default => $numero,
            };

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => 'Pedido #' . $numero,
                'value' => $value !== '' ? $value : $numero,
                'caption' => trim(($cliente !== '' ? $cliente : 'Sin cliente') . ' | ' . ($articulo !== '' ? $articulo : 'Sin articulo') . ' | ' . ($clasificacion !== '' ? $clasificacion : 'Sin clasificacion')),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    public function create(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        View::render('app/modules/CrmPedidosServicio/views/form.php', array_merge($this->buildUiContext(), [
            'formMode' => 'create',
            'formAction' => '/mi-empresa/crm/pedidos-servicio',
            'pedido' => $this->defaultFormState($empresaId),
            'errors' => [],
        ]));
    }

    public function store(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        try {
            $payload = $this->validateRequest($_POST, $empresaId, null, $_POST['action'] ?? '');
            $pedidoId = $this->repository->create($payload);
            if (!empty($_POST['capturas_diagnostico_base64'])) {
                $this->repository->syncAdjuntos($pedidoId, $empresaId, $_POST['capturas_diagnostico_base64']);
            }

            if (($_POST['action'] ?? '') === 'tango') {
                $tangoService = new PedidoServicioTangoService();
                $res = $tangoService->send($pedidoId, $empresaId);
                if ($res['ok']) {
                    Flash::set('success', 'Pedido de servicio creado y ' . $res['message']);
                } else {
                    Flash::set('danger', 'Pedido creado exitosamente, pero falló el envío a Tango: ' . $res['message']);
                }
            } else {
                Flash::set('success', 'Pedido de servicio creado correctamente.');
            }
            
            header('Location: /mi-empresa/crm/pedidos-servicio/' . $pedidoId . '/editar');
            exit;
        } catch (ValidationException $e) {
            http_response_code(422);
            View::render('app/modules/CrmPedidosServicio/views/form.php', array_merge($this->buildUiContext(), [
                'formMode' => 'create',
                'formAction' => '/mi-empresa/crm/pedidos-servicio',
                'pedido' => $this->buildFormStateFromPost($_POST, $empresaId),
                'errors' => $e->errors(),
            ]));
        }
    }

    public function edit(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $pedido = $this->repository->findById((int) $id, $empresaId);

        if ($pedido === null) {
            Flash::set('danger', 'El pedido de servicio no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/pedidos-servicio');
            exit;
        }

        View::render('app/modules/CrmPedidosServicio/views/form.php', array_merge($this->buildUiContext(), [
            'formMode' => 'edit',
            'formAction' => '/mi-empresa/crm/pedidos-servicio/' . (int) $pedido['id'],
            'pedido' => $this->hydrateFormState($pedido),
            'errors' => [],
        ]));
    }

    public function update(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $pedidoActual = $this->repository->findById((int) $id, $empresaId);

        if ($pedidoActual === null) {
            Flash::set('danger', 'El pedido de servicio no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/pedidos-servicio');
            exit;
        }

        try {
            $payload = $this->validateRequest($_POST, $empresaId, $pedidoActual, $_POST['action'] ?? '');
            $this->repository->update((int) $id, $empresaId, $payload);
            if (!empty($_POST['capturas_diagnostico_base64'])) {
                $this->repository->syncAdjuntos((int) $id, $empresaId, $_POST['capturas_diagnostico_base64']);
            }
            
            if (($_POST['action'] ?? '') === 'tango') {
                $tangoService = new PedidoServicioTangoService();
                $res = $tangoService->send((int) $id, $empresaId);
                if ($res['ok']) {
                    Flash::set('success', $res['message']);
                } else {
                    Flash::set('danger', $res['message']);
                }
            } else {
                Flash::set('success', 'Pedido de servicio actualizado correctamente.');
            }
            
            header('Location: /mi-empresa/crm/pedidos-servicio/' . (int) $id . '/editar');
            exit;
        } catch (ValidationException $e) {
            http_response_code(422);
            View::render('app/modules/CrmPedidosServicio/views/form.php', array_merge($this->buildUiContext(), [
                'formMode' => 'edit',
                'formAction' => '/mi-empresa/crm/pedidos-servicio/' . (int) $id,
                'pedido' => $this->buildFormStateFromPost($_POST, $empresaId, $pedidoActual),
                'errors' => $e->errors(),
            ]));
        }
    }

    public function copy(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $pedidoOriginal = $this->repository->findById((int) $id, $empresaId);

        if ($pedidoOriginal === null) {
            Flash::set('danger', 'El pedido de servicio base no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/pedidos-servicio');
            exit;
        }

        try {
            $data = $pedidoOriginal;
            unset($data['id'], $data['numero']);

            $data['fecha_inicio'] = date('Y-m-d H:i:s');
            $data['fecha_finalizado'] = null;
            $data['descuento_segundos'] = 0;
            $data['duracion_bruta_segundos'] = null;
            $data['duracion_neta_segundos'] = null;
            
            // Limpieza requerida para estado y sync con ERP
            $data['nro_pedido'] = null;
            $data['tango_sync_status'] = null;
            $data['tango_sync_error'] = null;
            $data['tango_sync_payload'] = null;
            $data['tango_sync_response'] = null;

            $data['usuario_id'] = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $data['usuario_nombre'] = $_SESSION['user_name'] ?? 'Usuario';

            $nuevoId = $this->repository->create($data);

            Flash::set('success', 'Pedido de servicio copiado exitosamente.');
            header('Location: /mi-empresa/crm/pedidos-servicio/' . $nuevoId . '/editar');
            exit;
        } catch (\Throwable $e) {
            Flash::set('danger', 'Falla al copiar el pedido de servicio: ' . $e->getMessage());
            header('Location: /mi-empresa/crm/pedidos-servicio/' . (int) $id . '/editar');
            exit;
        }
    }

    public function printPreview(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $pedido = $this->repository->findById((int) $id, $empresaId);

        if ($pedido === null) {
            Flash::set('danger', 'El pedido de servicio no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/pedidos-servicio');
            exit;
        }

        $contextBuilder = new PedidoServicioPrintContextBuilder();
        $context = $contextBuilder->build($empresaId, $pedido);

        $printRepo = new \App\Modules\PrintForms\PrintFormRepository();
        $renderer = new \App\Modules\PrintForms\PrintFormRenderer();

        try {
            $configRepo = \App\Modules\EmpresaConfig\EmpresaConfigRepository::forCrm();
            $config = $configRepo->findByEmpresaId($empresaId);
            $canvasId = $config->pds_email_pdf_canvas_id ?? null;

            if ($canvasId) {
                $template = $printRepo->resolveTemplateByDefinitionId($empresaId, (int)$canvasId);
            } else {
                $template = $printRepo->resolveTemplateForDocument($empresaId, 'crm_pedido_servicio');
            }

            $rendered = $renderer->buildDocument(
                $template['page_config'] ?? [],
                $template['objects'] ?? [],
                $context,
                (string) ($template['background_url'] ?? '')
            );

            $html = \App\Core\View::renderToString('app/modules/PrintForms/views/document_render.php', [
                'title' => 'Pedido de Servicio #' . $pedido['numero'],
                'subtitle' => 'Cliente: ' . ($pedido['cliente_nombre'] ?? 'Sin nombre'),
                'page' => $rendered['page'],
                'renderedObjects' => $rendered['objects'],
                'hideToolbar' => true,
            ]);

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultPaperSize', 'A4');
            
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->render();
            
            $filename = 'PedidoServicio_' . str_pad((string)$pedido['numero'], 6, '0', STR_PAD_LEFT) . '.pdf';
            $dompdf->stream($filename, ["Attachment" => false]);
            exit;
        } catch (\Throwable $e) {
            Flash::set('danger', 'No se pudo generar la impresion: ' . $e->getMessage());
            header('Location: /mi-empresa/crm/pedidos-servicio/' . (int) $id . '/editar');
            exit;
        }
    }

    public function sendEmail(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $pedido = $this->repository->findById((int) $id, $empresaId);

        if ($pedido === null) {
            Flash::set('danger', 'El pedido de servicio no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/pedidos-servicio');
            exit;
        }

        $cliente = $this->repository->findClientById($empresaId, (int) $pedido['cliente_id']);
        $email = $cliente['email'] ?? '';
        
        if (trim((string)$email) === '') {
            if (trim((string)($pedido['cliente_email'] ?? '')) !== '') {
                $email = $pedido['cliente_email'];
            } else {
                Flash::set('danger', 'El cliente asociado no tiene un correo electronico configurado.');
                header('Location: /mi-empresa/crm/pedidos-servicio/' . (int) $id . '/editar');
                exit;
            }
        }

        $contextBuilder = new PedidoServicioPrintContextBuilder();
        $contextData = $contextBuilder->build($empresaId, $pedido);

        $mailer = new \App\Shared\Services\DocumentMailerService();
        $filename = 'PedidoServicio_' . str_pad((string)$pedido['numero'], 6, '0', STR_PAD_LEFT);
        
        
        $adjuntos = [];
        $capturasDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/pds-diagnostico/';
        $dbAdjuntos = $this->repository->getAdjuntos((int) $id);
        
        foreach ($dbAdjuntos as $adjunto) {
            $path = str_replace('/uploads/pds-diagnostico/', $capturasDir, $adjunto['file_path']);
            if (is_file($path)) {
                $adjuntos[] = [
                    'path' => $path,
                    'filename' => $adjunto['label'] ?? 'adjunto.jpg'
                ];
            }
        }

        try {
            $success = $mailer->sendDocument(
                $empresaId,
                trim((string)$email),
                $contextData,
                'pds',
                'crm_pds',
                'Tu Pedido de Servicio #' . $pedido['numero'],
                $filename,
                $adjuntos
            );

            if ($success) {
                Flash::set('success', 'Email enviado correctamente a ' . htmlspecialchars((string)$email));
            } else {
                Flash::set('danger', 'Error al enviar el correo. Revise su configuracion SMTP en Empresa -> Configuracion.');
            }
        } catch (\Throwable $e) {
            Flash::set('danger', 'Falla en la generacion/envio de documento: ' . $e->getMessage());
        }

        header('Location: /mi-empresa/crm/pedidos-servicio/' . (int) $id . '/editar');
        exit;
    }

    public function clientSuggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $term = trim((string) ($_GET['q'] ?? ''));

        $rows = $this->repository->findClientSuggestions($empresaId, $term, 50);
        $data = array_map(static function (array $row): array {
            $label = trim((string) ($row['razon_social'] ?? ''));
            if ($label === '') {
                $label = trim(((string) ($row['nombre'] ?? '')) . ' ' . ((string) ($row['apellido'] ?? '')));
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $label !== '' ? $label : 'Cliente',
                'value' => $label !== '' ? $label : 'Cliente',
                'caption' => trim('#' . (int) ($row['id'] ?? 0) . ' | ' . ((string) ($row['email'] ?? '') !== '' ? (string) $row['email'] : ((string) ($row['documento'] ?? '') !== '' ? (string) $row['documento'] : 'Sin referencia'))),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public function articleSuggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $term = trim((string) ($_GET['q'] ?? ''));

        $rows = $this->repository->findArticleSuggestions($empresaId, $term, 50);
        $data = array_map(static function (array $row): array {
            $nombre = trim((string) ($row['nombre'] ?? 'Articulo'));
            $codigo = trim((string) ($row['codigo_externo'] ?? ''));

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $nombre,
                'value' => $nombre,
                'caption' => trim('#' . (int) ($row['id'] ?? 0) . ' | ' . ($codigo !== '' ? $codigo : 'Sin codigo')),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public function classificationSuggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $term = mb_strtolower(trim((string) ($_GET['q'] ?? '')));
        $config = \App\Modules\EmpresaConfig\EmpresaConfigService::forCrm()->getConfig();
        
        $raw = trim((string)($config->clasificaciones_pds_raw ?? ''));
        $useRaw = false;
        $items = [];
        
        if ($raw !== '') {
            $parsed = json_decode($raw, true);
            if (is_array($parsed)) {
                $items = $parsed;
                $useRaw = true;
            }
        }
        
        if (!$useRaw) {
            $token = trim((string) ($config->tango_connect_token ?? ''));
            if ($token !== '') {
                try {
                    $client = new \App\Modules\Tango\TangoApiClient(
                        rtrim((string) ($config->tango_api_url ?? ''), '/') . '/Api',
                        $token,
                        trim((string) ($config->tango_connect_company_id ?? '')),
                        trim((string) ($config->tango_connect_key ?? '')) ?: null
                    );
                    
                    // Petición ágil y directa (1 sola hoja grande para traer todo sin loops lentos)
                    $data = $client->getRawClient()->get('Get', [
                        'process' => 326,
                        'pageSize' => 150,
                        'pageIndex' => 0,
                        'view' => ''
                    ]);
                    $items = $data['resultData']['list'] ?? $data['data']['resultData']['list'] ?? [];
                } catch (\Throwable $e) {
                    $items = [];
                }
            }
        }

        $filtered = [];
        foreach ($items as $item) {
            $extraId = $item['ID_GVA81'] ?? ($item['id'] ?? '');
            $code = $item['COD_GVA81'] ?? ($item['codigo'] ?? '');
            $desc = $item['DESCRIP'] ?? ($item['descripcion'] ?? '');
            
            if ($term !== '') {
                $codeMatch = str_contains(mb_strtolower((string)$code), $term);
                $descMatch = str_contains(mb_strtolower((string)$desc), $term);
                if (!$codeMatch && !$descMatch) {
                    continue;
                }
            }
            
            // `id`: el cod para guardar local en `clasificacion_codigo`
            // `value`: lo que se ve en el input text del picker
            // `extraId`: lo que va a ir a `clasificacion_id_tango` para armar el requerimiento de Tango
            $filtered[] = [
                'id' => trim((string)$code),
                'extraId' => $extraId, 
                'value' => mb_substr(trim((string)$code), 0, 80),
                'label' => trim((string)$desc) !== '' ? trim((string)$code) . ' - ' . trim((string)$desc) : trim((string)$code),
                'caption' => 'Clasificación PDS',
            ];
        }
        
        usort($filtered, static function($a, $b) use ($term) {
            if ($term === '') return strnatcasecmp($a['id'], $b['id']);
            $codeAStarts = str_starts_with(mb_strtolower($a['id']), $term);
            $codeBStarts = str_starts_with(mb_strtolower($b['id']), $term);
            if ($codeAStarts && !$codeBStarts) return -1;
            if (!$codeAStarts && $codeBStarts) return 1;
            return strnatcasecmp($a['id'], $b['id']);
        });

        echo json_encode(['success' => true, 'data' => array_slice($filtered, 0, 50)], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private function validateRequest(array $input, int $empresaId, ?array $pedidoActual, string $action = ''): array
    {
        $errors = [];
        $fechaInicioInput = trim((string) ($input['fecha_inicio'] ?? ''));
        $fechaFinalizadoInput = trim((string) ($input['fecha_finalizado'] ?? ''));
        $solicito = trim((string) ($input['solicito'] ?? ''));
        $nroPedido = trim((string) ($input['nro_pedido'] ?? ''));
        $clasificacion = strtoupper(trim((string) ($input['clasificacion_codigo'] ?? '')));
        $clasificacionIdTango = trim((string) ($input['clasificacion_id_tango'] ?? ''));
        $diagnostico = trim((string) ($input['diagnostico'] ?? ''));
        $motivo_descuento = trim((string) ($input['motivo_descuento'] ?? ''));
        $descuentoInput = trim((string) ($input['descuento'] ?? '00:00:00'));
        $clienteId = (int) ($input['cliente_id'] ?? 0);
        $articuloId = (int) ($input['articulo_id'] ?? 0);

        $fechaInicio = $this->parseDateTimeInput($fechaInicioInput);
        if ($fechaInicio === null) {
            $errors['fecha_inicio'] = 'Ingresa una fecha y hora de inicio valida.';
        }

        $fechaFinalizado = null;
        if ($fechaFinalizadoInput === '') {
            if ($action === 'tango') {
                $errors['fecha_finalizado'] = 'La fecha de finalización es obligatoria para enviar a Tango (Finalizado).';
            }
        } else {
            $fechaFinalizado = $this->parseDateTimeInput($fechaFinalizadoInput);
            if ($fechaFinalizado === null) {
                $errors['fecha_finalizado'] = 'La fecha de finalizacion no tiene un formato valido.';
            }
        }

        if ($solicito === '') {
            $errors['solicito'] = 'Debes indicar quien solicito el servicio.';
        }

        $descuentoSegundos = $this->parseDuration($descuentoInput);
        if ($descuentoSegundos === null) {
            $errors['descuento'] = 'El descuento debe respetar el formato HH:MM:SS.';
        } elseif ($descuentoSegundos > 0 && $motivo_descuento === '') {
            $errors['motivo_descuento'] = 'Debes indicar por que se aplica un descuento.';
        }

        $cliente = null;
        if ($clienteId <= 0) {
            $errors['cliente_id'] = 'Selecciona un cliente desde la base disponible.';
        } else {
            $cliente = $this->repository->findClientById($empresaId, $clienteId);
            if ($cliente === null && $pedidoActual !== null && (int) ($pedidoActual['cliente_id'] ?? 0) === $clienteId) {
                $cliente = [
                    'id' => $clienteId,
                    'razon_social' => $pedidoActual['cliente_nombre'] ?? '',
                    'nombre' => $pedidoActual['cliente_nombre'] ?? '',
                    'apellido' => '',
                    'email' => $pedidoActual['cliente_email'] ?? null,
                    'documento' => $pedidoActual['cliente_documento'] ?? null,
                ];
            }
            if ($cliente === null) {
                $errors['cliente_id'] = 'El cliente seleccionado ya no esta disponible.';
            }
        }

        $articulo = null;
        if ($articuloId <= 0) {
            $errors['articulo_id'] = 'Selecciona un articulo existente de la base.';
        } else {
            $articulo = $this->repository->findArticleById($empresaId, $articuloId);
            if ($articulo === null && $pedidoActual !== null && (int) ($pedidoActual['articulo_id'] ?? 0) === $articuloId) {
                $articulo = [
                    'id' => $articuloId,
                    'codigo_externo' => $pedidoActual['articulo_codigo'] ?? null,
                    'nombre' => $pedidoActual['articulo_nombre'] ?? '',
                ];
            }
            if ($articulo === null) {
                $errors['articulo_id'] = 'El articulo seleccionado ya no esta disponible.';
            }
        }

        if ($fechaInicio !== null && $fechaFinalizado !== null && $fechaFinalizado < $fechaInicio) {
            $errors['fecha_finalizado'] = 'La fecha final no puede ser anterior al inicio.';
        }

        $duracionBruta = null;
        $duracionNeta = null;
        if ($fechaInicio !== null && $fechaFinalizado !== null) {
            $duracionBruta = max(0, $fechaFinalizado->getTimestamp() - $fechaInicio->getTimestamp());
            if ($descuentoSegundos !== null && $descuentoSegundos > $duracionBruta) {
                $errors['descuento'] = 'El descuento no puede superar el tiempo total del servicio.';
            }

            if ($descuentoSegundos !== null) {
                $duracionNeta = $duracionBruta - $descuentoSegundos;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $clienteNombre = trim((string) ($cliente['razon_social'] ?? ''));
        if ($clienteNombre === '') {
            $clienteNombre = trim(((string) ($cliente['nombre'] ?? '')) . ' ' . ((string) ($cliente['apellido'] ?? '')));
        }

        return [
            'empresa_id' => $empresaId,
            'usuario_id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            'usuario_nombre' => $_SESSION['user_name'] ?? 'Usuario',
            'fecha_inicio' => $fechaInicio?->format('Y-m-d H:i:s'),
            'fecha_finalizado' => $fechaFinalizado?->format('Y-m-d H:i:s'),
            'cliente_id' => $clienteId,
            'cliente_fuente' => 'crm_clientes',
            'cliente_nombre' => $clienteNombre !== '' ? $clienteNombre : 'Cliente #' . $clienteId,
            'cliente_documento' => $cliente['documento'] ?? null,
            'cliente_email' => $cliente['email'] ?? null,
            'solicito' => $solicito,
            'nro_pedido' => $nroPedido !== '' ? $nroPedido : null,
            'articulo_id' => $articuloId,
            'articulo_codigo' => $articulo['codigo_externo'] ?? null,
            'articulo_nombre' => trim((string) ($articulo['nombre'] ?? '')),
            'clasificacion_codigo' => $clasificacion !== '' ? $clasificacion : null,
            'clasificacion_id_tango' => $clasificacionIdTango !== '' && is_numeric($clasificacionIdTango) ? (int)$clasificacionIdTango : null,
            'descuento_segundos' => $descuentoSegundos,
            'diagnostico' => $diagnostico !== '' ? $diagnostico : null,
            'motivo_descuento' => $motivo_descuento !== '' ? $motivo_descuento : null,
            'duracion_bruta_segundos' => $duracionBruta,
            'duracion_neta_segundos' => $duracionNeta,
            'tiempo_decimal' => $duracionNeta !== null ? round(max(0, $duracionNeta) / 3600, 4) : null,
        ];
    }

    private function buildUiContext(): array
    {
        return [
            'basePath' => '/mi-empresa/crm/pedidos-servicio',
            'dashboardPath' => OperationalAreaService::dashboardPath(OperationalAreaService::AREA_CRM),
            'helpPath' => OperationalAreaService::helpPath(OperationalAreaService::AREA_CRM),
            'moduleNotesKey' => 'crm_pedidos_servicio',
            'moduleNotesLabel' => 'Pedidos de Servicio CRM',
        ];
    }

    private function normalizeSearchField(string $field): string
    {
        return in_array($field, self::SEARCH_FIELDS, true) ? $field : 'all';
    }

    private function defaultFormState(int $empresaId): array
    {
        $now = new DateTimeImmutable();

        $clienteId = '';
        $clienteNombre = '';
        if (!empty($_GET['cliente_id'])) {
            $cli = $this->repository->findClientById($empresaId, (int)$_GET['cliente_id']);
            if ($cli) {
                $clienteId = $cli['id'];
                $razon = trim((string)($cli['razon_social'] ?? ''));
                $clienteNombre = $razon !== '' ? $razon : trim(($cli['nombre'] ?? '') . ' ' . ($cli['apellido'] ?? ''));
            }
        }

        return [
            'id' => null,
            'numero' => $this->repository->previewNextNumero($empresaId),
            'fecha_inicio' => isset($_GET['inicio']) ? trim($_GET['inicio']) : $now->format('Y-m-d\TH:i:s'),
            'fecha_finalizado' => isset($_GET['fin']) ? trim($_GET['fin']) : '',
            'cliente_id' => $clienteId,
            'cliente_nombre' => $clienteNombre,
            'solicito' => '',
            'nro_pedido' => '',
            'articulo_id' => '',
            'articulo_nombre' => '',
            'clasificacion_codigo' => '',
            'clasificacion_id_tango' => '',
            'descuento' => '00:00:00',
            'diagnostico' => isset($_GET['diagnostico']) ? trim($_GET['diagnostico']) : '',
            'motivo_descuento' => '',
            'duracion_bruta_segundos' => null,
            'duracion_neta_segundos' => null,
            'duracion_bruta_hhmmss' => '--:--:--',
            'duracion_neta_hhmmss' => '--:--:--',
            'estado_ui' => 'abierto',
            'capturas' => [],
        ];
    }

    private function hydrateFormState(array $pedido): array
    {
        return [
            'id' => (int) ($pedido['id'] ?? 0),
            'numero' => (int) ($pedido['numero'] ?? 0),
            'fecha_inicio' => $this->formatDateTimeForInput($pedido['fecha_inicio'] ?? null),
            'fecha_finalizado' => $this->formatDateTimeForInput($pedido['fecha_finalizado'] ?? null),
            'cliente_id' => (string) ($pedido['cliente_id'] ?? ''),
            'cliente_nombre' => (string) ($pedido['cliente_nombre'] ?? ''),
            'solicito' => (string) ($pedido['solicito'] ?? ''),
            'nro_pedido' => (string) ($pedido['nro_pedido'] ?? ''),
            'articulo_id' => (string) ($pedido['articulo_id'] ?? ''),
            'articulo_nombre' => (string) ($pedido['articulo_nombre'] ?? ''),
            'clasificacion_codigo' => (string) ($pedido['clasificacion_codigo'] ?? ''),
            'clasificacion_id_tango' => (string) ($pedido['clasificacion_id_tango'] ?? ''),
            'descuento' => $this->formatDuration((int) ($pedido['descuento_segundos'] ?? 0)),
            'diagnostico' => (string) ($pedido['diagnostico'] ?? ''),
            'motivo_descuento' => (string) ($pedido['motivo_descuento'] ?? ''),
            'duracion_bruta_segundos' => isset($pedido['duracion_bruta_segundos']) ? (int) $pedido['duracion_bruta_segundos'] : null,
            'duracion_neta_segundos' => isset($pedido['duracion_neta_segundos']) ? (int) $pedido['duracion_neta_segundos'] : null,
            'duracion_bruta_hhmmss' => isset($pedido['duracion_bruta_segundos']) ? $this->formatDuration((int) $pedido['duracion_bruta_segundos']) : '--:--:--',
            'duracion_neta_hhmmss' => isset($pedido['duracion_neta_segundos']) ? $this->formatDuration((int) $pedido['duracion_neta_segundos']) : '--:--:--',
            'estado_ui' => empty($pedido['fecha_finalizado']) ? 'abierto' : 'finalizado',
            'tango_sync_payload' => $pedido['tango_sync_payload'] ?? null,
            'tango_sync_response' => $pedido['tango_sync_response'] ?? null,
            'capturas' => array_map(static function(array $adj) {
                return [
                    'id' => $adj['id'],
                    'url' => $adj['file_path'],
                    'label' => $adj['label'] ?? ''
                ];
            }, $this->repository->getAdjuntos((int) ($pedido['id'] ?? 0))),
        ];
    }

    private function buildFormStateFromPost(array $input, int $empresaId, ?array $pedidoActual = null): array
    {
        $state = $pedidoActual !== null ? $this->hydrateFormState($pedidoActual) : $this->defaultFormState($empresaId);
        $state['fecha_inicio'] = trim((string) ($input['fecha_inicio'] ?? $state['fecha_inicio']));
        $state['fecha_finalizado'] = trim((string) ($input['fecha_finalizado'] ?? $state['fecha_finalizado']));
        $state['cliente_id'] = trim((string) ($input['cliente_id'] ?? $state['cliente_id']));
        $state['cliente_nombre'] = trim((string) ($input['cliente_nombre'] ?? $state['cliente_nombre']));
        $state['solicito'] = trim((string) ($input['solicito'] ?? $state['solicito']));
        $state['nro_pedido'] = trim((string) ($input['nro_pedido'] ?? $state['nro_pedido']));
        $state['articulo_id'] = trim((string) ($input['articulo_id'] ?? $state['articulo_id']));
        $state['articulo_nombre'] = trim((string) ($input['articulo_nombre'] ?? $state['articulo_nombre']));
        $state['clasificacion_codigo'] = strtoupper(trim((string) ($input['clasificacion_codigo'] ?? $state['clasificacion_codigo'])));
        $state['clasificacion_id_tango'] = trim((string) ($input['clasificacion_id_tango'] ?? $state['clasificacion_id_tango']));
        $state['descuento'] = trim((string) ($input['descuento'] ?? $state['descuento']));
        $state['diagnostico'] = trim((string) ($input['diagnostico'] ?? $state['diagnostico']));
        $state['motivo_descuento'] = trim((string) ($input['motivo_descuento'] ?? $state['motivo_descuento']));

        $inicio = $this->parseDateTimeInput($state['fecha_inicio']);
        $fin = $this->parseDateTimeInput($state['fecha_finalizado']);
        $descuento = $this->parseDuration($state['descuento']);
        $duracionBruta = null;
        $duracionNeta = null;

        if ($inicio !== null && $fin !== null) {
            $duracionBruta = max(0, $fin->getTimestamp() - $inicio->getTimestamp());
            if ($descuento !== null && $descuento <= $duracionBruta) {
                $duracionNeta = $duracionBruta - $descuento;
            }
        }

        $state['duracion_bruta_segundos'] = $duracionBruta;
        $state['duracion_neta_segundos'] = $duracionNeta;
        $state['duracion_bruta_hhmmss'] = $duracionBruta !== null ? $this->formatDuration($duracionBruta) : '--:--:--';
        $state['duracion_neta_hhmmss'] = $duracionNeta !== null ? $this->formatDuration($duracionNeta) : '--:--:--';
        $state['estado_ui'] = $state['fecha_finalizado'] !== '' ? 'finalizado' : 'abierto';

        $state['capturas'] = $state['capturas'] ?? [];
        if (!empty($input['capturas_diagnostico_base64']) && is_array($input['capturas_diagnostico_base64'])) {
            foreach ($input['capturas_diagnostico_base64'] as $b64Str) {
                $decoded = json_decode((string) $b64Str, true);
                if (is_array($decoded) && isset($decoded['data'], $decoded['label'])) {
                    $state['capturas'][] = [
                        'id' => 'temp_' . uniqid(),
                        'url' => $decoded['data'],
                        'label' => $decoded['label'],
                        'is_temp' => $b64Str
                    ];
                }
            }
        }

        return $state;
    }

    private function parseDateTimeInput(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s'];
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date;
            }
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private function formatDateTimeForInput(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d\TH:i:s');
        } catch (Exception) {
            return '';
        }
    }

    private function parseDuration(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        if (!preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $value, $matches)) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        $seconds = (int) $matches[3];

        if ($minutes > 59 || $seconds > 59) {
            return null;
        }

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    private function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remaining);
    }
}

final class ValidationException extends \RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(private array $errors)
    {
        parent::__construct('Los datos enviados no son validos.');
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

