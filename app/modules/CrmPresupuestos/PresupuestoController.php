<?php
declare(strict_types=1);

namespace App\Modules\CrmPresupuestos;

use App\Core\Context;
use App\Core\Flash;
use App\Core\View;
use App\Modules\Articulos\ArticuloRepository;
use App\Modules\Auth\AuthService;
use App\Modules\CrmClientes\CrmClienteRepository;
use App\Modules\EmpresaConfig\EmpresaConfigRepository;
use App\Modules\RxnSync\Services\CommercialCatalogSyncService;
use App\Shared\Services\OperationalAreaService;
use DateTimeImmutable;
use Throwable;

class PresupuestoController extends \App\Core\Controller
{
    private const SEARCH_FIELDS = ['all', 'numero', 'cliente', 'estado', 'fecha'];
    private const ESTADOS = ['borrador', 'emitido', 'anulado'];
    private const CATALOG_TYPES = ['deposito', 'condicion_venta', 'lista_precio', 'vendedor', 'transporte'];

    private PresupuestoRepository $repository;
    private CrmClienteRepository $clienteRepository;
    private CommercialCatalogRepository $catalogRepository;
    private CommercialCatalogSyncService $catalogSyncService;
    private EmpresaConfigRepository $configRepository;

    public function __construct()
    {
        $this->repository = new PresupuestoRepository();
        $this->clienteRepository = new CrmClienteRepository();
        $this->catalogRepository = new CommercialCatalogRepository();
        $this->catalogSyncService = new CommercialCatalogSyncService();
        $this->configRepository = EmpresaConfigRepository::forCrm();
    }

    public function index(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = isset($_GET['limit']) ? (int) ($_GET['limit']) : 25;
        if (!in_array($limit, [25, 50, 100], true)) {
            $limit = 25;
        }

        $search = trim((string) ($_GET['search'] ?? ''));
        $field = $this->normalizeSearchField((string) ($_GET['field'] ?? 'all'));
        $estado = trim((string) ($_GET['estado'] ?? ''));
        $sort = trim((string) ($_GET['sort'] ?? 'fecha'));
        $dir = strtoupper((string) ($_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $advancedFilters = $this->handleCrudFilters('crm_presupuestos');

        $totalItems = $this->repository->countAll($empresaId, $search, $field, $estado, $advancedFilters);
        $totalPages = max(1, (int) ceil($totalItems / $limit));
        $page = min($page, $totalPages);
        $presupuestos = $this->repository->findAllPaginated($empresaId, $page, $limit, $search, $field, $estado, $sort, $dir, $advancedFilters);

        View::render('app/modules/CrmPresupuestos/views/index.php', array_merge($this->buildUiContext(), [
            'presupuestos' => $presupuestos,
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
            $cliente = trim((string) ($row['cliente_nombre_snapshot'] ?? ''));
            $fecha = trim((string) ($row['fecha'] ?? ''));
            $estadoUi = trim((string) ($row['estado'] ?? 'borrador'));

            $value = match ($field) {
                'cliente' => $cliente,
                'estado' => $estadoUi,
                'fecha' => $fecha,
                default => $numero,
            };

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => 'Presupuesto #' . $numero,
                'value' => $value !== '' ? $value : $numero,
                'caption' => trim(($cliente !== '' ? $cliente : 'Sin cliente') . ' | ' . ($fecha !== '' ? $fecha : 'Sin fecha') . ' | ' . strtoupper($estadoUi)),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    public function create(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $catalogData = $this->loadCatalogData($empresaId);

        View::render('app/modules/CrmPresupuestos/views/form.php', array_merge($this->buildUiContext(), [
            'formMode' => 'create',
            'formAction' => '/mi-empresa/crm/presupuestos',
            'presupuesto' => $this->defaultFormState($empresaId),
            'catalogs' => $catalogData['catalogs'],
            'catalogSyncWarning' => $catalogData['warning'],
            'errors' => [],
        ]));
    }

    public function store(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        try {
            $payload = $this->validateRequest($_POST, $empresaId, null);
            $presupuestoId = $this->repository->create($payload);

            // Geo-tracking: registrar evento de creación. Fire-and-forget.
            // Ver app/modules/RxnGeoTracking/MODULE_CONTEXT.md.
            try {
                $geoService = new \App\Modules\RxnGeoTracking\GeoTrackingService();
                $geoEventoId = $geoService->registrar(
                    \App\Modules\RxnGeoTracking\GeoTrackingService::EVENT_PRESUPUESTO_CREATED,
                    $presupuestoId,
                    'presupuesto'
                );
                if ($geoEventoId !== null) {
                    $_SESSION['rxn_geo_pending_event_id'] = $geoEventoId;
                }
            } catch (\Throwable) {
                // Silent fail — el presupuesto ya está guardado.
            }

            Flash::set('success', 'Presupuesto CRM guardado correctamente.');
            header('Location: ' . $this->resolveReturnPath($presupuestoId, (int) ($payload['tratativa_id'] ?? 0)));
            exit;
        } catch (ValidationException $e) {
            http_response_code(422);
            $catalogData = $this->loadCatalogData($empresaId);
            View::render('app/modules/CrmPresupuestos/views/form.php', array_merge($this->buildUiContext(), [
                'formMode' => 'create',
                'formAction' => '/mi-empresa/crm/presupuestos',
                'presupuesto' => $this->buildFormStateFromPost($_POST, $empresaId),
                'catalogs' => $catalogData['catalogs'],
                'catalogSyncWarning' => $catalogData['warning'],
                'errors' => $e->errors(),
            ]));
        }
    }

    public function edit(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $presupuesto = $this->repository->findById((int) $id, $empresaId);

        if ($presupuesto === null) {
            Flash::set('danger', 'El presupuesto CRM no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/presupuestos');
            exit;
        }

        $catalogData = $this->loadCatalogData($empresaId);
        $items = $this->repository->findItemsByPresupuestoId((int) $presupuesto['id'], $empresaId);

        View::render('app/modules/CrmPresupuestos/views/form.php', array_merge($this->buildUiContext(), [
            'formMode' => 'edit',
            'formAction' => '/mi-empresa/crm/presupuestos/' . (int) $presupuesto['id'],
            'presupuesto' => $this->hydrateFormState($presupuesto, $items),
            'catalogs' => $catalogData['catalogs'],
            'catalogSyncWarning' => $catalogData['warning'],
            'errors' => [],
            // ?from_copy=1 → suspende el lock de cabecera en este primer render
            // post-copia. Lo lee el JS del form. Ver PresupuestoController::copy().
            'isFromCopy' => !empty($_GET['from_copy']),
        ]));
    }

    public function update(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $presupuestoActual = $this->repository->findById((int) $id, $empresaId);

        if ($presupuestoActual === null) {
            Flash::set('danger', 'El presupuesto CRM no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/presupuestos');
            exit;
        }

        try {
            $payload = $this->validateRequest($_POST, $empresaId, $presupuestoActual);
            $this->repository->update((int) $id, $empresaId, $payload);
            Flash::set('success', 'Presupuesto CRM actualizado correctamente.');
            header('Location: ' . $this->resolveReturnPath((int) $id, (int) ($payload['tratativa_id'] ?? 0)));
            exit;
        } catch (ValidationException $e) {
            http_response_code(422);
            $catalogData = $this->loadCatalogData($empresaId);
            View::render('app/modules/CrmPresupuestos/views/form.php', array_merge($this->buildUiContext(), [
                'formMode' => 'edit',
                'formAction' => '/mi-empresa/crm/presupuestos/' . (int) $id,
                'presupuesto' => $this->buildFormStateFromPost($_POST, $empresaId, $presupuestoActual),
                'catalogs' => $catalogData['catalogs'],
                'catalogSyncWarning' => $catalogData['warning'],
                'errors' => $e->errors(),
            ]));
        }
    }

    public function copy(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $presupuestoOriginal = $this->repository->findById((int) $id, $empresaId);

        if ($presupuestoOriginal === null) {
            Flash::set('danger', 'El presupuesto CRM base no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/presupuestos');
            exit;
        }

        try {
            $data = $presupuestoOriginal;

            unset($data['id'], $data['numero']);

            $data['fecha'] = (new \DateTimeImmutable())->format('Y-m-d\TH:i:s');
            $data['estado'] = 'borrador';

            $data['usuario_id'] = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $data['usuario_nombre'] = $_SESSION['user_name'] ?? 'Usuario';

            $itemsOriginales = $this->repository->findItemsByPresupuestoId((int) $id, $empresaId);
            $itemsNuevos = [];
            foreach ($itemsOriginales as $item) {
                unset($item['id'], $item['presupuesto_id']);
                $item['articulo_descripcion'] = $item['articulo_descripcion_snapshot'] ?? '';
                $itemsNuevos[] = $item;
            }
            $data['items'] = $itemsNuevos;

            $nuevoId = $this->repository->create($data);

            // Bandera ?from_copy=1: la consume el JS del form para NO aplicar el
            // lock de cabecera en este primer render. Sin esto, el operador no
            // podría editar cliente/fecha/lista en la copia hasta borrar todos los
            // renglones, lo cual es exactamente lo contrario de lo que quiere
            // (toda la gracia de copiar es heredar items y ajustar la cabecera).
            // Al primer submit el flag desaparece de la URL y vuelve la lógica normal.
            Flash::set('success', 'Presupuesto CRM copiado exitosamente.');
            header('Location: /mi-empresa/crm/presupuestos/' . $nuevoId . '/editar?from_copy=1');
            exit;
        } catch (Throwable $e) {
            Flash::set('danger', 'Falla al copiar el presupuesto CRM: ' . $e->getMessage());
            header('Location: /mi-empresa/crm/presupuestos/' . (int) $id . '/editar');
            exit;
        }
    }

    /**
     * Genera una nueva versión del presupuesto (release 1.29.x — versionado).
     *
     * A diferencia de copy(), esta acción crea una versión VINCULADA al original
     * mediante version_padre_id (raíz del grupo) + version_numero secuencial.
     * Permite trazar la cadena de iteraciones desde el header del form de la versión.
     */
    public function nuevaVersion(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $idInt = (int) $id;
        if ($idInt <= 0) {
            Flash::set('danger', 'ID de presupuesto inválido.');
            header('Location: /mi-empresa/crm/presupuestos');
            exit;
        }

        try {
            $usuarioId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
            $usuarioNombre = $_SESSION['user_name'] ?? 'Usuario';
            $nuevoId = $this->repository->createNewVersion($idInt, $empresaId, $usuarioId, $usuarioNombre);
            Flash::set('success', 'Nueva versión generada exitosamente.');
            header('Location: /mi-empresa/crm/presupuestos/' . $nuevoId . '/editar');
            exit;
        } catch (\Throwable $e) {
            error_log('[PresupuestoController::nuevaVersion] Falla: ' . $e->getMessage());
            Flash::set('danger', 'No se pudo generar la nueva versión: ' . $e->getMessage());
            header('Location: /mi-empresa/crm/presupuestos/' . $idInt . '/editar');
            exit;
        }
    }

    public function eliminar(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $idInt = (int) $id;
        
        if ($idInt > 0) {
            $this->repository->deleteByIds([$idInt], $empresaId);
            Flash::set('success', 'Presupuesto enviado a la papelera.');
        }

        header('Location: /mi-empresa/crm/presupuestos');
        exit;
    }

    public function eliminarMasivo(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (!empty($ids) && is_array($ids)) {
                $count = $this->repository->deleteByIds(array_map('intval', $ids), $empresaId);
                Flash::set('success', "{$count} presupuestos enviados a la papelera.");
            }
        }
        
        header('Location: /mi-empresa/crm/presupuestos');
        exit;
    }

    public function restore(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $idInt = (int) $id;
        
        if ($idInt > 0) {
            $this->repository->restoreByIds([$idInt], $empresaId);
            Flash::set('success', 'Presupuesto restaurado.');
        }

        header('Location: /mi-empresa/crm/presupuestos?status=papelera');
        exit;
    }

    public function restoreMasivo(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (!empty($ids) && is_array($ids)) {
                $count = $this->repository->restoreByIds(array_map('intval', $ids), $empresaId);
                Flash::set('success', "{$count} presupuestos restaurados.");
            }
        }
        
        header('Location: /mi-empresa/crm/presupuestos?status=papelera');
        exit;
    }

    public function forceDelete(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $idInt = (int) $id;
        
        if ($idInt > 0) {
            $this->repository->forceDeleteByIds([$idInt], $empresaId);
            Flash::set('success', 'Presupuesto eliminado definitivamente.');
        }

        header('Location: /mi-empresa/crm/presupuestos?status=papelera');
        exit;
    }

    public function forceDeleteMasivo(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (!empty($ids) && is_array($ids)) {
                $count = $this->repository->forceDeleteByIds(array_map('intval', $ids), $empresaId);
                Flash::set('success', "{$count} presupuestos eliminados definitivamente.");
            }
        }
        
        header('Location: /mi-empresa/crm/presupuestos?status=papelera');
        exit;
    }

    public function printPreview(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $presupuesto = $this->repository->findById((int) $id, $empresaId);

        if ($presupuesto === null) {
            Flash::set('danger', 'El presupuesto CRM no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/presupuestos');
            exit;
        }

        $items = $this->repository->findItemsByPresupuestoId((int) $id, $empresaId);
        $contextBuilder = new CrmPresupuestoPrintContextBuilder();
        $context = $contextBuilder->build($empresaId, $presupuesto, $items);

        $printRepo = new \App\Modules\PrintForms\PrintFormRepository();
        $renderer = new \App\Modules\PrintForms\PrintFormRenderer();

        try {
            $configRepo = \App\Modules\EmpresaConfig\EmpresaConfigRepository::forCrm();
            $config = $configRepo->findByEmpresaId($empresaId);
            $canvasId = $config->presupuesto_email_pdf_canvas_id ?? null;

            if ($canvasId) {
                $template = $printRepo->resolveTemplateByDefinitionId($empresaId, (int)$canvasId);
            } else {
                $template = $printRepo->resolveTemplateForDocument($empresaId, 'crm_presupuesto');
            }

            $rendered = $renderer->buildDocument(
                $template['page_config'] ?? [],
                $template['objects'] ?? [],
                $context,
                (string) ($template['background_url'] ?? '')
            );

            $html = \App\Core\View::renderToString('app/modules/PrintForms/views/document_render.php', [
                'title' => 'Presupuesto CRM #' . $presupuesto['numero'],
                'subtitle' => 'Cliente: ' . ($presupuesto['cliente_nombre_snapshot'] ?? 'Sin nombre'),
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
            
            $filename = 'Presupuesto_' . str_pad((string)$presupuesto['numero'], 6, '0', STR_PAD_LEFT) . '.pdf';
            $dompdf->stream($filename, ["Attachment" => false]);
            exit;
        } catch (\Throwable $e) {
            Flash::set('danger', 'No se pudo generar la impresion: ' . $e->getMessage());
            header('Location: /mi-empresa/crm/presupuestos/' . (int) $id . '/editar');
            exit;
        }
    }

    public function sendEmail(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $presupuesto = $this->repository->findById((int) $id, $empresaId);

        if ($presupuesto === null) {
            Flash::set('danger', 'El presupuesto CRM no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/presupuestos');
            exit;
        }

        // Obtener mail del cliente
        $cliente = $this->clienteRepository->findById((int) $presupuesto['cliente_id'], $empresaId);
        $email = $cliente['email'] ?? '';
        if (trim((string)$email) === '') {
            Flash::set('danger', 'El cliente asociado no tiene un correo electrónico configurado.');
            header('Location: /mi-empresa/crm/presupuestos/' . (int) $id . '/editar');
            exit;
        }

        $items = $this->repository->findItemsByPresupuestoId((int) $id, $empresaId);
        $contextBuilder = new CrmPresupuestoPrintContextBuilder();
        $contextData = $contextBuilder->build($empresaId, $presupuesto, $items);

        $mailer = new \App\Shared\Services\DocumentMailerService();
        $filename = 'Presupuesto_' . str_pad((string)$presupuesto['numero'], 6, '0', STR_PAD_LEFT);
        
        try {
            $success = $mailer->sendDocument(
                $empresaId,
                trim((string)$email),
                $contextData,
                'presupuesto',
                'crm_presupuesto',
                'Tu Presupuesto Comercial #' . $presupuesto['numero'],
                $filename
            );

            if ($success) {
                $this->repository->registrarCorreoEnviado((int) $id, $empresaId);
                Flash::set('success', 'Email enviado correctamente a ' . htmlspecialchars((string)$email));
            } else {
                $this->repository->registrarErrorCorreo((int) $id, $empresaId, 'Error SMTP al enviar el correo. Revisá la configuración SMTP de la empresa.');
                Flash::set('danger', 'Error al enviar el correo. Revise su configuración SMTP en Empresa -> Configuración.');
            }
        } catch (\Throwable $e) {
            $this->repository->registrarErrorCorreo((int) $id, $empresaId, $e->getMessage());
            Flash::set('danger', 'Falla en la generación/envío de documento: ' . $e->getMessage());
        }

        header('Location: /mi-empresa/crm/presupuestos/' . (int) $id . '/editar');
        exit;
    }

    public function syncTango(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        
        $presupuesto = $this->repository->findById((int) $id, $empresaId);

        if ($presupuesto === null) {
            Flash::set('danger', 'El presupuesto CRM no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/presupuestos');
            exit;
        }

        $service = new PresupuestoTangoService($this->repository);
        $result = $service->send((int) $id, $empresaId);

        Flash::set($result['type'] ?? 'info', $result['message'] ?? 'Sincronización procesada.');

        header('Location: /mi-empresa/crm/presupuestos/' . (int) $id . '/editar');
        exit;
    }

    public function clientSuggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $term = trim((string) ($_GET['q'] ?? ''));
        // Sin guard de longitud mínima: al abrir el Spotlight Modal con Enter, el frontend
        // hace fetch con q='' y esperamos que el backend devuelva los primeros resultados
        // (comportamiento alineado con PedidoServicioController::clientSuggestions).
        // Límite dinámico: sin término pedimos 30 para que el operador pueda scrollear;
        // con término suficiente para match preciso, 5 por relevancia.
        $limit = $term === '' ? 30 : 5;

        $rows = $this->clienteRepository->findSuggestions($empresaId, $term, 'all', $limit);
        $data = array_map(static function (array $row): array {
            $label = trim((string) ($row['razon_social'] ?? ''));
            $codigoTango = trim((string) ($row['codigo_tango'] ?? ''));
            $documento = trim((string) ($row['documento'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $captionParts = ['#' . (int) ($row['id'] ?? 0)];

            if ($codigoTango !== '') {
                $captionParts[] = $codigoTango;
            }
            if ($documento !== '') {
                $captionParts[] = $documento;
            } elseif ($email !== '') {
                $captionParts[] = $email;
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $label !== '' ? $label : 'Cliente',
                'value' => $label !== '' ? $label : 'Cliente',
                'caption' => implode(' | ', $captionParts),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    public function clientContext(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $clienteId = (int) ($_GET['id'] ?? 0);

        if ($clienteId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Cliente invalido.', 'data' => []]);
            exit;
        }

        $catalogData = $this->loadCatalogData($empresaId);
        $cliente = $this->clienteRepository->findById($clienteId, $empresaId);
        if ($cliente === null) {
            echo json_encode(['success' => false, 'message' => 'El cliente seleccionado ya no existe en CRM.', 'data' => []]);
            exit;
        }

        $defaults = $this->loadUserTangoProfileDefaults($empresaId);
        $payload = [
            'cliente' => [
                'id' => (int) $cliente['id'],
                'nombre' => trim((string) ($cliente['razon_social'] ?? '')),
                'documento' => trim((string) ($cliente['documento'] ?? '')),
            ],
            'deposito' => $this->resolveCatalogSelection($empresaId, 'deposito', $defaults['deposito_codigo'] ?? null, 'Deposito'),
            'condicion' => $this->resolveCatalogSelection($empresaId, 'condicion_venta', $cliente['id_gva01_condicion_venta'] ?? $cliente['id_gva23_tango'] ?? ($defaults['condicion_codigo'] ?? null), 'Condicion'),
            'lista' => $this->resolveCatalogSelection($empresaId, 'lista_precio', $cliente['id_gva10_lista_precios'] ?? $cliente['id_gva10_tango'] ?? ($defaults['lista_codigo'] ?? null), 'Lista'),
            'vendedor' => $this->resolveCatalogSelection($empresaId, 'vendedor', $cliente['id_gva23_vendedor'] ?? $cliente['id_gva01_tango'] ?? ($defaults['vendedor_codigo'] ?? null), 'Vendedor'),
            'transporte' => $this->resolveCatalogSelection($empresaId, 'transporte', $cliente['id_gva24_transporte'] ?? $cliente['id_gva24_tango'] ?? ($defaults['transporte_codigo'] ?? null), 'Transporte'),
            'warning' => $catalogData['warning'],
        ];

        echo json_encode(['success' => true, 'data' => $payload]);
        exit;
    }

    public function articleSuggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $term = trim((string) ($_GET['q'] ?? ''));
        // Sin guard de longitud mínima: al abrir el Spotlight Modal con Enter, el frontend
        // hace fetch con q='' y esperamos que el backend devuelva los primeros resultados
        // (comportamiento alineado con PedidoServicioController::articleSuggestions).
        // Límite dinámico: sin término 30 (scroll cómodo), con término 6 (relevancia).
        $limit = $term === '' ? 30 : 6;

        $rows = ArticuloRepository::forCrm()->findSuggestions($empresaId, $term, 'all', $limit);
        $data = array_map(static function (array $row): array {
            $nombre = trim((string) ($row['nombre'] ?? ''));
            $codigo = trim((string) ($row['codigo_externo'] ?? ''));
            $descripcion = trim((string) ($row['descripcion'] ?? ''));
            $captionParts = ['#' . (int) ($row['id'] ?? 0)];

            if ($codigo !== '') {
                $captionParts[] = $codigo;
            }
            if ($descripcion !== '') {
                $captionParts[] = $descripcion;
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $nombre !== '' ? $nombre : ($codigo !== '' ? $codigo : 'Articulo'),
                'value' => $nombre !== '' ? $nombre : ($codigo !== '' ? $codigo : 'Articulo'),
                'caption' => implode(' | ', $captionParts),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    public function articleContext(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $articuloId = (int) ($_GET['id'] ?? 0);
        $listaCodigo = trim((string) ($_GET['lista_codigo'] ?? ''));
        $depositoCodigo = trim((string) ($_GET['deposito_codigo'] ?? ''));

        if ($articuloId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Articulo invalido.', 'data' => []]);
            exit;
        }

        $articulo = $this->repository->findArticleContext($empresaId, $articuloId, $listaCodigo, $depositoCodigo !== '' ? $depositoCodigo : null);
        if ($articulo === null) {
            echo json_encode(['success' => false, 'message' => 'El articulo seleccionado ya no existe en CRM.', 'data' => []]);
            exit;
        }

        echo json_encode(['success' => true, 'data' => [
            'articulo_id' => (int) $articulo['id'],
            'articulo_codigo' => (string) ($articulo['codigo_externo'] ?? ''),
            'articulo_descripcion' => trim((string) ($articulo['nombre'] ?? '')),
            'articulo_detalle' => trim((string) ($articulo['descripcion'] ?? '')),
            'precio_unitario' => $articulo['precio_unitario'] !== null ? round((float) $articulo['precio_unitario'], 4) : null,
            'precio_origen' => (string) ($articulo['precio_origen'] ?? 'manual'),
            'lista_codigo_aplicada' => $listaCodigo !== '' ? $listaCodigo : null,
            'stock_deposito' => $articulo['stock_deposito'],
        ]]);
        exit;
    }

    private function buildUiContext(): array
    {
        return [
            'pageTitle' => 'Presupuestos CRM',
            'dashboardPath' => OperationalAreaService::dashboardPath(OperationalAreaService::AREA_CRM),
            'helpPath' => OperationalAreaService::helpPath(OperationalAreaService::AREA_CRM),
            'basePath' => '/mi-empresa/crm/presupuestos',
            'moduleNotesKey' => 'crm_presupuestos',
            'moduleNotesLabel' => 'Presupuestos CRM',
            // Sync Catalogos vive ahora en RxnSync (release 1.12.5). Ver /mi-empresa/crm/rxn-sync.
            'syncCatalogosPath' => '/mi-empresa/crm/rxn-sync',
        ];
    }

    /**
     * Guardar = quedarse en el presupuesto (coherente con PDS v1.19.0). El botón Volver
     * del form es el que lleva a la tratativa si el presupuesto vive bajo una tratativa.
     */
    private function resolveReturnPath(int $presupuestoId, int $tratativaId): string
    {
        return '/mi-empresa/crm/presupuestos/' . $presupuestoId . '/editar';
    }

    private function loadCatalogData(int $empresaId): array
    {
        $warning = null;

        foreach (self::CATALOG_TYPES as $type) {
            if ($this->catalogRepository->countByType($empresaId, $type) === 0) {
                try {
                    $this->catalogSyncService->sync($empresaId);
                } catch (Throwable $e) {
                    $warning = 'No se pudieron refrescar automaticamente los catalogos comerciales CRM. Puedes seguir operando, pero conviene revisar la configuracion o usar Sync Catalogos.';
                }
                break;
            }
        }

        return [
            'warning' => $warning,
            'catalogs' => [
                'depositos' => $this->catalogRepository->findAllByType($empresaId, 'deposito'),
                'condiciones' => $this->catalogRepository->findAllByType($empresaId, 'condicion_venta'),
                'listas' => $this->catalogRepository->findAllByType($empresaId, 'lista_precio'),
                'vendedores' => $this->catalogRepository->findAllByType($empresaId, 'vendedor'),
                'transportes' => $this->catalogRepository->findAllByType($empresaId, 'transporte'),
            ],
        ];
    }

    private function loadUserTangoProfileDefaults(int $empresaId): array
    {
        $deposito = '';
        $condicion = '';
        $transporte = '';
        $lista = '';
        $vendedor = '';

        if (isset($_SESSION['user_id'])) {
            $userRepo = new \App\Modules\Auth\UsuarioRepository();
            $usuario = $userRepo->findById((int)$_SESSION['user_id']);
            if ($usuario && $usuario->tango_perfil_snapshot_json) {
                $snap = json_decode($usuario->tango_perfil_snapshot_json, true) ?: [];
                $raw = (!empty($snap['raw']) && is_array($snap['raw'])) ? $snap['raw'] : [];
                $profileData = array_change_key_case(array_merge($raw, $snap), CASE_UPPER);

                $vendedor = trim((string)($profileData['ID_GVA01_VENDEDOR'] ?? $profileData['ID_GVA01'] ?? ''));
                $condicion = trim((string)($profileData['ID_GVA23_ENCABEZADO'] ?? $profileData['ID_GVA23_CONDICION_VENTA'] ?? $profileData['ID_GVA23'] ?? ''));
                $deposito = trim((string)($profileData['ID_STA22_DEPOSITO'] ?? $profileData['ID_STA22'] ?? ''));
                $lista = trim((string)($profileData['ID_GVA10_ENCABEZADO'] ?? $profileData['ID_GVA10_ZONA'] ?? $profileData['ID_GVA10'] ?? ''));
                $transporte = trim((string)($profileData['ID_GVA24_TRANSPORTE'] ?? $profileData['ID_GVA24'] ?? ''));
            }
        }

        // Si el perfil Tango trae un codigo, verificamos que exista en el catalogo CRM actual;
        // si no existe (por ejemplo un ID_GVA10 viejo que ya no esta sincronizado), caemos al primero
        // disponible. Evita mostrar "Guardado localmente (N)" en el form para un nuevo presupuesto.
        $resolveDefault = function (int $empresaId, string $type, string $code): string {
            if ($code !== '' && $this->catalogRepository->findOption($empresaId, $type, $code) !== null) {
                return $code;
            }
            $first = $this->catalogRepository->findFirstByType($empresaId, $type);
            return $first ? trim((string) $first['codigo']) : '';
        };

        $deposito = $resolveDefault($empresaId, 'deposito', $deposito);
        $condicion = $resolveDefault($empresaId, 'condicion_venta', $condicion);
        $transporte = $resolveDefault($empresaId, 'transporte', $transporte);
        $lista = $resolveDefault($empresaId, 'lista_precio', $lista);
        $vendedor = $resolveDefault($empresaId, 'vendedor', $vendedor);

        return [
            'deposito_codigo' => $deposito,
            'condicion_codigo' => $condicion,
            'transporte_codigo' => $transporte,
            'lista_codigo' => $lista,
            'vendedor_codigo' => $vendedor,
        ];
    }

    private function resolveCatalogSelection(int $empresaId, string $type, mixed $code, string $fallbackLabel): array
    {
        $normalizedCode = trim((string) $code);
        $option = $normalizedCode !== '' ? $this->catalogRepository->findOption($empresaId, $type, $normalizedCode) : null;

        return [
            'codigo' => $normalizedCode !== '' ? $normalizedCode : '',
            'descripcion' => trim((string) ($option['descripcion'] ?? '')) !== ''
                ? trim((string) $option['descripcion'])
                : ($normalizedCode !== '' ? $fallbackLabel . ' ' . $normalizedCode : ''),
            'id_interno' => $option !== null && $option['id_interno'] !== null ? (int) $option['id_interno'] : null,
        ];
    }

    private function defaultFormState(int $empresaId): array
    {
        $now = new DateTimeImmutable();
        $defaults = $this->loadUserTangoProfileDefaults($empresaId);

        // Tratativa vinculada desde query param (patron identico a Llamadas -> PDS con ?inicio=)
        $tratativaId = '';
        if (!empty($_GET['tratativa_id'])) {
            $tratativaRepo = new \App\Modules\CrmTratativas\TratativaRepository();
            if ($tratativaRepo->existsActiveForEmpresa((int) $_GET['tratativa_id'], $empresaId)) {
                $tratativaId = (string) (int) $_GET['tratativa_id'];
            }
        }

        // Cliente precargado desde query param (tipico al crear desde detalle de tratativa)
        $clienteIdPreload = '';
        $clienteNombrePreload = '';
        if (!empty($_GET['cliente_id'])) {
            $cli = $this->clienteRepository->findById((int) $_GET['cliente_id'], $empresaId);
            if ($cli) {
                $clienteIdPreload = (string) $cli['id'];
                $razon = trim((string) ($cli['razon_social'] ?? ''));
                $clienteNombrePreload = $razon !== '' ? $razon : trim(((string) ($cli['nombre'] ?? '')) . ' ' . ((string) ($cli['apellido'] ?? '')));
            }
        }

        return [
            'id' => null,
            'numero' => $this->repository->previewNextNumero($empresaId),
            'tratativa_id' => $tratativaId,
            'fecha' => $now->format('Y-m-d\TH:i:s'),
            'estado' => 'borrador',
            'cotizacion' => 1.0,
            'proximo_contacto' => '',
            'vigencia' => '',
            'leyenda_1' => '',
            'leyenda_2' => '',
            'leyenda_3' => '',
            'leyenda_4' => '',
            'leyenda_5' => '',
            'cliente_id' => $clienteIdPreload,
            'cliente_nombre' => $clienteNombrePreload,
            'cliente_documento' => '',
            'deposito_codigo' => $defaults['deposito_codigo'] ?? '',
            'condicion_codigo' => $defaults['condicion_codigo'] ?? '',
            'transporte_codigo' => $defaults['transporte_codigo'] ?? '',
            'lista_codigo' => $defaults['lista_codigo'] ?? '',
            'clasificacion_codigo' => '',
            'clasificacion_id_tango' => '',
            'clasificacion_descripcion' => '',
            'vendedor_codigo' => $defaults['vendedor_codigo'] ?? '',
            'subtotal' => 0.0,
            'descuento_total' => 0.0,
            'impuestos_total' => 0.0,
            'total' => 0.0,
            'tango_sync_status' => '',
            'nro_comprobante_tango' => '',
            'tango_sync_date' => '',
            'tango_sync_log' => '',
            'items' => [],
        ];
    }

    private function hydrateFormState(array $presupuesto, array $items): array
    {
        return [
            'id' => (int) ($presupuesto['id'] ?? 0),
            'numero' => (int) ($presupuesto['numero'] ?? 0),
            'tratativa_id' => (string) ($presupuesto['tratativa_id'] ?? ''),
            'version_padre_id' => isset($presupuesto['version_padre_id']) && $presupuesto['version_padre_id'] !== null ? (int) $presupuesto['version_padre_id'] : 0,
            'version_numero' => (int) ($presupuesto['version_numero'] ?? 1),
            'fecha' => $this->formatDateTimeForInput($presupuesto['fecha'] ?? null),
            'estado' => (string) ($presupuesto['estado'] ?? 'borrador'),
            'cotizacion' => isset($presupuesto['cotizacion']) ? (float) $presupuesto['cotizacion'] : 1.0,
            'proximo_contacto' => $this->formatDateTimeForInput($presupuesto['proximo_contacto'] ?? null),
            'vigencia' => $this->formatDateTimeForInput($presupuesto['vigencia'] ?? null),
            'leyenda_1' => (string) ($presupuesto['leyenda_1'] ?? ''),
            'leyenda_2' => (string) ($presupuesto['leyenda_2'] ?? ''),
            'leyenda_3' => (string) ($presupuesto['leyenda_3'] ?? ''),
            'leyenda_4' => (string) ($presupuesto['leyenda_4'] ?? ''),
            'leyenda_5' => (string) ($presupuesto['leyenda_5'] ?? ''),
            'cliente_id' => (string) ($presupuesto['cliente_id'] ?? ''),
            'cliente_nombre' => (string) ($presupuesto['cliente_nombre_snapshot'] ?? ''),
            'cliente_documento' => (string) ($presupuesto['cliente_documento_snapshot'] ?? ''),
            'deposito_codigo' => (string) ($presupuesto['deposito_codigo'] ?? ''),
            'deposito_nombre_snapshot' => (string) ($presupuesto['deposito_nombre_snapshot'] ?? ''),
            'condicion_codigo' => (string) ($presupuesto['condicion_codigo'] ?? ''),
            'condicion_nombre_snapshot' => (string) ($presupuesto['condicion_nombre_snapshot'] ?? ''),
            'condicion_id_interno' => (string) ($presupuesto['condicion_id_interno'] ?? ''),
            'transporte_codigo' => (string) ($presupuesto['transporte_codigo'] ?? ''),
            'transporte_nombre_snapshot' => (string) ($presupuesto['transporte_nombre_snapshot'] ?? ''),
            'transporte_id_interno' => (string) ($presupuesto['transporte_id_interno'] ?? ''),
            'lista_codigo' => (string) ($presupuesto['lista_codigo'] ?? ''),
            'lista_nombre_snapshot' => (string) ($presupuesto['lista_nombre_snapshot'] ?? ''),
            'lista_id_interno' => (string) ($presupuesto['lista_id_interno'] ?? ''),
            'vendedor_codigo' => (string) ($presupuesto['vendedor_codigo'] ?? ''),
            'vendedor_nombre_snapshot' => (string) ($presupuesto['vendedor_nombre_snapshot'] ?? ''),
            'vendedor_id_interno' => (string) ($presupuesto['vendedor_id_interno'] ?? ''),
            'clasificacion_codigo' => (string) ($presupuesto['clasificacion_codigo'] ?? ''),
            'clasificacion_id_tango' => (string) ($presupuesto['clasificacion_id_tango'] ?? ''),
            'clasificacion_descripcion' => (string) ($presupuesto['clasificacion_descripcion'] ?? ''),
            'subtotal' => isset($presupuesto['subtotal']) ? (float) $presupuesto['subtotal'] : 0.0,
            'descuento_total' => isset($presupuesto['descuento_total']) ? (float) $presupuesto['descuento_total'] : 0.0,
            'impuestos_total' => isset($presupuesto['impuestos_total']) ? (float) $presupuesto['impuestos_total'] : 0.0,
            'total' => isset($presupuesto['total']) ? (float) $presupuesto['total'] : 0.0,
            'tango_sync_status' => (string) ($presupuesto['tango_sync_status'] ?? ''),
            'nro_comprobante_tango' => (string) ($presupuesto['nro_comprobante_tango'] ?? ''),
            'tango_sync_date' => (string) ($presupuesto['tango_sync_date'] ?? ''),
            'tango_sync_log' => (string) ($presupuesto['tango_sync_log'] ?? ''),
            'items' => array_map(static function (array $item): array {
                $original = (string) ($item['articulo_descripcion_original'] ?? $item['articulo_descripcion_snapshot'] ?? '');
                return [
                    'articulo_id' => (string) ($item['articulo_id'] ?? ''),
                    'articulo_codigo' => (string) ($item['articulo_codigo'] ?? ''),
                    'articulo_descripcion' => (string) ($item['articulo_descripcion_snapshot'] ?? ''),
                    'articulo_descripcion_original' => $original,
                    'cantidad' => isset($item['cantidad']) ? (float) $item['cantidad'] : 1.0,
                    'precio_unitario' => isset($item['precio_unitario']) ? (float) $item['precio_unitario'] : 0.0,
                    'bonificacion_porcentaje' => isset($item['bonificacion_porcentaje']) ? (float) $item['bonificacion_porcentaje'] : 0.0,
                    'importe_bruto' => isset($item['importe_bruto']) ? (float) $item['importe_bruto'] : 0.0,
                    'importe_neto' => isset($item['importe_neto']) ? (float) $item['importe_neto'] : 0.0,
                    'precio_origen' => (string) ($item['precio_origen'] ?? 'manual'),
                    'lista_codigo_aplicada' => (string) ($item['lista_codigo_aplicada'] ?? ''),
                ];
            }, $items),
            'correos_enviados_count' => (int) ($presupuesto['correos_enviados_count'] ?? 0),
            'correos_ultimo_envio_at' => $presupuesto['correos_ultimo_envio_at'] ?? null,
            'correos_ultimo_error' => $presupuesto['correos_ultimo_error'] ?? null,
            'correos_ultimo_error_at' => $presupuesto['correos_ultimo_error_at'] ?? null,
        ];
    }

    private function buildFormStateFromPost(array $input, int $empresaId, ?array $presupuestoActual = null): array
    {
        $state = $presupuestoActual !== null
            ? $this->hydrateFormState($presupuestoActual, $this->repository->findItemsByPresupuestoId((int) $presupuestoActual['id'], $empresaId))
            : $this->defaultFormState($empresaId);

        $state['tratativa_id'] = trim((string) ($input['tratativa_id'] ?? $state['tratativa_id'] ?? ''));
        $state['fecha'] = trim((string) ($input['fecha'] ?? $state['fecha']));
        $state['estado'] = $this->normalizeEstado((string) ($input['estado'] ?? $state['estado']));
        $state['cotizacion'] = isset($input['cotizacion']) && $input['cotizacion'] !== '' ? (float) $this->normalizeDecimal($input['cotizacion']) : ($state['cotizacion'] ?? 1.0);
        $state['proximo_contacto'] = trim((string) ($input['proximo_contacto'] ?? $state['proximo_contacto'] ?? ''));
        $state['vigencia'] = trim((string) ($input['vigencia'] ?? $state['vigencia'] ?? ''));
        for ($i = 1; $i <= 5; $i++) {
            $key = 'leyenda_' . $i;
            $state[$key] = mb_substr(trim((string) ($input[$key] ?? $state[$key] ?? '')), 0, 60);
        }
        $state['cliente_id'] = trim((string) ($input['cliente_id'] ?? $state['cliente_id']));
        $state['cliente_nombre'] = trim((string) ($input['cliente_nombre'] ?? $state['cliente_nombre']));
        $state['cliente_documento'] = trim((string) ($input['cliente_documento'] ?? $state['cliente_documento']));
        $state['deposito_codigo'] = trim((string) ($input['deposito_codigo'] ?? $state['deposito_codigo']));
        $state['condicion_codigo'] = trim((string) ($input['condicion_codigo'] ?? $state['condicion_codigo']));
        $state['transporte_codigo'] = trim((string) ($input['transporte_codigo'] ?? $state['transporte_codigo']));
        $state['lista_codigo'] = trim((string) ($input['lista_codigo'] ?? $state['lista_codigo']));
        $state['vendedor_codigo'] = trim((string) ($input['vendedor_codigo'] ?? $state['vendedor_codigo']));
        $state['clasificacion_codigo'] = trim((string) ($input['clasificacion_codigo'] ?? $state['clasificacion_codigo']));
        $state['clasificacion_id_tango'] = trim((string) ($input['clasificacion_id_tango'] ?? $state['clasificacion_id_tango']));
        $state['clasificacion_descripcion'] = trim((string) ($input['clasificacion_descripcion'] ?? ($state['clasificacion_descripcion'] ?? '')));
        $state['items'] = $this->normalizePostedItems($input['items'] ?? [], $state['lista_codigo']);

        $totals = $this->calculateTotals($state['items']);
        $state['subtotal'] = $totals['subtotal'];
        $state['descuento_total'] = $totals['descuento_total'];
        $state['impuestos_total'] = $totals['impuestos_total'];
        $state['total'] = $totals['total'];
        $state['items'] = $totals['items'];

        return $state;
    }

    private function validateRequest(array $input, int $empresaId, ?array $presupuestoActual): array
    {
        $errors = [];

        // Tratativa vinculada (opcional): viene desde query param o como hidden del form
        $tratativaIdInput = (int) ($input['tratativa_id'] ?? 0);
        if ($tratativaIdInput <= 0 && $presupuestoActual !== null) {
            $tratativaIdInput = (int) ($presupuestoActual['tratativa_id'] ?? 0);
        }
        $tratativaIdFinal = null;
        if ($tratativaIdInput > 0) {
            $tratativaRepo = new \App\Modules\CrmTratativas\TratativaRepository();
            if ($tratativaRepo->existsActiveForEmpresa($tratativaIdInput, $empresaId)) {
                $tratativaIdFinal = $tratativaIdInput;
            }
        }

        $fechaInput = trim((string) ($input['fecha'] ?? ''));
        $fecha = $this->parseDateTimeInput($fechaInput);
        if ($fecha === null) {
            $errors['fecha'] = 'Ingresa una fecha valida para el presupuesto.';
        }

        // Próximo contacto y Vigencia: ambos opcionales (DATETIME nullable). Se aceptan
        // los mismos formatos que la fecha del presupuesto (ISO con T o con espacio).
        $proximoContactoInput = trim((string) ($input['proximo_contacto'] ?? ''));
        $proximoContacto = $proximoContactoInput !== '' ? $this->parseDateTimeInput($proximoContactoInput) : null;
        if ($proximoContactoInput !== '' && $proximoContacto === null) {
            $errors['proximo_contacto'] = 'Fecha y hora de "Próximo contacto" inválida.';
        }

        $vigenciaInput = trim((string) ($input['vigencia'] ?? ''));
        $vigencia = $vigenciaInput !== '' ? $this->parseDateTimeInput($vigenciaInput) : null;
        if ($vigenciaInput !== '' && $vigencia === null) {
            $errors['vigencia'] = 'Fecha y hora de "Vigencia" inválida.';
        }

        // Cotización: numérica >= 0. Default 1 si vacío.
        $cotizacionRaw = $input['cotizacion'] ?? null;
        $cotizacion = ($cotizacionRaw === null || $cotizacionRaw === '')
            ? 1.0
            : (float) $this->normalizeDecimal($cotizacionRaw);
        if ($cotizacion < 0) {
            $errors['cotizacion'] = 'La cotización no puede ser negativa.';
        }

        // Leyendas 1..5: opcionales, máximo 60 caracteres cada una. Trim + truncado defensivo.
        $leyendas = [];
        for ($i = 1; $i <= 5; $i++) {
            $key = 'leyenda_' . $i;
            $value = trim((string) ($input[$key] ?? ''));
            if ($value !== '' && mb_strlen($value) > 60) {
                $value = mb_substr($value, 0, 60);
            }
            $leyendas[$key] = $value !== '' ? $value : null;
        }

        $estado = $this->normalizeEstado((string) ($input['estado'] ?? 'borrador'));
        $clienteId = (int) ($input['cliente_id'] ?? 0);
        $clienteNombreInput = trim((string) ($input['cliente_nombre'] ?? ''));

        $cliente = null;
        if ($clienteId <= 0) {
            $errors['cliente_id'] = 'Selecciona un cliente desde la base CRM.';
        } else {
            $cliente = $this->clienteRepository->findById($clienteId, $empresaId);
            if ($cliente === null && $presupuestoActual !== null && (int) ($presupuestoActual['cliente_id'] ?? 0) === $clienteId) {
                $cliente = [
                    'id' => $clienteId,
                    'razon_social' => $presupuestoActual['cliente_nombre_snapshot'] ?? $clienteNombreInput,
                    'documento' => $presupuestoActual['cliente_documento_snapshot'] ?? null,
                ];
            }
            if ($cliente === null) {
                $errors['cliente_id'] = 'El cliente seleccionado ya no esta disponible en CRM.';
            }
        }

        $deposito = $this->resolveCatalogSelection($empresaId, 'deposito', $input['deposito_codigo'] ?? null, 'Deposito');
        $condicion = $this->resolveCatalogSelection($empresaId, 'condicion_venta', $input['condicion_codigo'] ?? null, 'Condicion');
        $lista = $this->resolveCatalogSelection($empresaId, 'lista_precio', $input['lista_codigo'] ?? null, 'Lista');
        $vendedor = $this->resolveCatalogSelection($empresaId, 'vendedor', $input['vendedor_codigo'] ?? null, 'Vendedor');
        $transporte = $this->resolveCatalogSelection($empresaId, 'transporte', $input['transporte_codigo'] ?? null, 'Transporte');

        if ($lista['codigo'] === '') {
            $errors['lista_codigo'] = 'Selecciona una lista de precios para el presupuesto.';
        }

        // Clasificación obligatoria (release 1.29.x — P0). El picker maneja
        // tanto el código como el nombre/descripción; con que el código exista
        // ya es válido — la descripción es display.
        $clasificacionCodigoInput = trim((string) ($input['clasificacion_codigo'] ?? ''));
        if ($clasificacionCodigoInput === '') {
            $errors['clasificacion_codigo'] = 'Selecciona una clasificación para el presupuesto.';
        }

        $items = $this->normalizePostedItems($input['items'] ?? [], $lista['codigo']);
        if ($items === []) {
            $errors['items'] = 'Agregá al menos un renglón al presupuesto.';
        }

        foreach ($items as $index => $item) {
            $rowNumber = $index + 1;
            if ($item['articulo_codigo'] === '' && $item['articulo_descripcion'] === '') {
                $errors['items_' . $index] = 'El renglon ' . $rowNumber . ' debe tener articulo o descripcion.';
                continue;
            }

            if ((float) $item['cantidad'] <= 0) {
                $errors['items_' . $index] = 'La cantidad del renglon ' . $rowNumber . ' debe ser mayor a cero.';
            }

            if ((float) $item['precio_unitario'] < 0) {
                $errors['items_' . $index] = 'El precio del renglon ' . $rowNumber . ' no puede ser negativo.';
            }

            if ((float) $item['bonificacion_porcentaje'] < 0 || (float) $item['bonificacion_porcentaje'] > 100) {
                $errors['items_' . $index] = 'La bonificacion del renglon ' . $rowNumber . ' debe estar entre 0 y 100.';
            }
        }

        $totals = $this->calculateTotals($items);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'empresa_id' => $empresaId,
            'tratativa_id' => $tratativaIdFinal,
            'usuario_id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            'usuario_nombre' => $_SESSION['user_name'] ?? 'Usuario',
            'fecha' => $fecha?->format('Y-m-d H:i:s'),
            'estado' => $estado,
            'cliente_id' => $clienteId,
            'cliente_nombre_snapshot' => trim((string) ($cliente['razon_social'] ?? $clienteNombreInput)) !== ''
                ? trim((string) ($cliente['razon_social'] ?? $clienteNombreInput))
                : 'Cliente #' . $clienteId,
            'cliente_documento_snapshot' => $cliente['documento'] ?? ($input['cliente_documento'] ?? null),
            'deposito_codigo' => $deposito['codigo'],
            'deposito_nombre_snapshot' => $deposito['descripcion'],
            'condicion_codigo' => $condicion['codigo'],
            'condicion_nombre_snapshot' => $condicion['descripcion'],
            'condicion_id_interno' => $condicion['id_interno'],
            'transporte_codigo' => $transporte['codigo'],
            'transporte_nombre_snapshot' => $transporte['descripcion'],
            'transporte_id_interno' => $transporte['id_interno'],
            'proximo_contacto' => $proximoContacto?->format('Y-m-d H:i:s'),
            'vigencia' => $vigencia?->format('Y-m-d H:i:s'),
            'leyenda_1' => $leyendas['leyenda_1'],
            'leyenda_2' => $leyendas['leyenda_2'],
            'leyenda_3' => $leyendas['leyenda_3'],
            'leyenda_4' => $leyendas['leyenda_4'],
            'leyenda_5' => $leyendas['leyenda_5'],
            'cotizacion' => $cotizacion,
            'lista_codigo' => $lista['codigo'],
            'lista_nombre_snapshot' => $lista['descripcion'],
            'lista_id_interno' => $lista['id_interno'],
            'clasificacion_codigo' => trim((string) ($input['clasificacion_codigo'] ?? '')),
            'clasificacion_id_tango' => trim((string) ($input['clasificacion_id_tango'] ?? '')),
            'clasificacion_descripcion' => trim((string) ($input['clasificacion_descripcion'] ?? '')),
            'vendedor_codigo' => $vendedor['codigo'],
            'vendedor_nombre_snapshot' => $vendedor['descripcion'],
            'vendedor_id_interno' => $vendedor['id_interno'],
            'subtotal' => $totals['subtotal'],
            'descuento_total' => $totals['descuento_total'],
            'impuestos_total' => $totals['impuestos_total'],
            'total' => $totals['total'],
            'items' => $totals['items'],
        ];
    }

    private function normalizePostedItems(mixed $inputItems, ?string $listaCodigo): array
    {
        if (!is_array($inputItems)) {
            return [];
        }

        $items = [];
        foreach ($inputItems as $row) {
            if (!is_array($row)) {
                continue;
            }

            $codigo = trim((string) ($row['articulo_codigo'] ?? ''));
            $descripcion = trim((string) ($row['articulo_descripcion'] ?? ''));
            $cantidad = $this->normalizeDecimal($row['cantidad'] ?? 1);
            $precioUnitario = $this->normalizeDecimal($row['precio_unitario'] ?? 0);
            $bonificacion = $this->normalizeDecimal($row['bonificacion_porcentaje'] ?? 0);

            if ($codigo === '' && $descripcion === '' && (float) $cantidad === 0.0 && (float) $precioUnitario === 0.0) {
                continue;
            }

            // Original viene del hidden que el JS setea al elegir el artículo
            // desde el picker. Si no viene (caso fila legacy o copy), caemos a la
            // descripción actual — esto deja al item marcado como "no modificado"
            // hasta el próximo save donde el operador edite.
            $original = trim((string) ($row['articulo_descripcion_original'] ?? ''));
            if ($original === '') {
                $original = $descripcion;
            }
            $items[] = [
                'orden' => count($items) + 1,
                'articulo_id' => $this->nullableInt($row['articulo_id'] ?? null),
                'articulo_codigo' => $codigo,
                'articulo_descripcion' => $descripcion,
                'articulo_descripcion_original' => $original,
                'cantidad' => (float) $cantidad,
                'precio_unitario' => (float) $precioUnitario,
                'bonificacion_porcentaje' => (float) $bonificacion,
                'importe_bruto' => 0.0,
                'importe_neto' => 0.0,
                'precio_origen' => trim((string) ($row['precio_origen'] ?? 'manual')) !== '' ? trim((string) $row['precio_origen']) : 'manual',
                'lista_codigo_aplicada' => trim((string) ($row['lista_codigo_aplicada'] ?? $listaCodigo ?? '')) !== ''
                    ? trim((string) ($row['lista_codigo_aplicada'] ?? $listaCodigo ?? ''))
                    : null,
            ];
        }

        return $items;
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = 0.0;
        $descuentoTotal = 0.0;
        $impuestosTotal = 0.0;

        foreach ($items as $index => $item) {
            $cantidad = max(0.0, (float) ($item['cantidad'] ?? 0));
            $precioUnitario = max(0.0, (float) ($item['precio_unitario'] ?? 0));
            $bonificacion = min(100.0, max(0.0, (float) ($item['bonificacion_porcentaje'] ?? 0)));
            $importeBruto = round($cantidad * $precioUnitario, 2);
            $importeNeto = round($importeBruto - ($importeBruto * $bonificacion / 100), 2);

            $items[$index]['orden'] = $index + 1;
            $items[$index]['cantidad'] = $cantidad;
            $items[$index]['precio_unitario'] = $precioUnitario;
            $items[$index]['bonificacion_porcentaje'] = $bonificacion;
            $items[$index]['importe_bruto'] = $importeBruto;
            $items[$index]['importe_neto'] = $importeNeto;

            $subtotal += $importeBruto;
            $descuentoTotal += ($importeBruto - $importeNeto);
        }

        return [
            'subtotal' => round($subtotal, 2),
            'descuento_total' => round($descuentoTotal, 2),
            'impuestos_total' => round($impuestosTotal, 2),
            'total' => round($subtotal - $descuentoTotal + $impuestosTotal, 2),
            'items' => $items,
        ];
    }

    private function normalizeSearchField(string $field): string
    {
        return in_array($field, self::SEARCH_FIELDS, true) ? $field : 'all';
    }

    private function normalizeEstado(string $estado): string
    {
        $estado = strtolower(trim($estado));
        return in_array($estado, self::ESTADOS, true) ? $estado : 'borrador';
    }

    private function parseDateTimeInput(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date;
            }
        }

        return null;
    }

    private function formatDateTimeForInput(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        // Devolvemos siempre con segundos. Flatpickr (rxn-datetime.js) hace su parte
        // con altFormat es-AR para mostrarlo `d/m/Y H:i:s`.
        $date = new DateTimeImmutable($value);
        return $date->format('Y-m-d\TH:i:s');
    }

    private function normalizeDecimal(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $normalized = str_replace([' ', ','], ['', '.'], trim((string) $value));
        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}

final class ValidationException extends \RuntimeException
{
    private array $errors;

    public function __construct(array $errors)
    {
        parent::__construct('El formulario contiene errores.');
        $this->errors = $errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}

