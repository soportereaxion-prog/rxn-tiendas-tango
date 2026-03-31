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
use App\Shared\Services\OperationalAreaService;
use DateTimeImmutable;
use Throwable;

class PresupuestoController
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

        $totalItems = $this->repository->countAll($empresaId, $search, $field, $estado);
        $totalPages = max(1, (int) ceil($totalItems / $limit));
        $page = min($page, $totalPages);
        $presupuestos = $this->repository->findAllPaginated($empresaId, $page, $limit, $search, $field, $estado, $sort, $dir);

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
            'formAction' => '/rxnTiendasIA/public/mi-empresa/crm/presupuestos',
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
            Flash::set('success', 'Presupuesto CRM guardado correctamente.');
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/presupuestos/' . $presupuestoId . '/editar');
            exit;
        } catch (ValidationException $e) {
            http_response_code(422);
            $catalogData = $this->loadCatalogData($empresaId);
            View::render('app/modules/CrmPresupuestos/views/form.php', array_merge($this->buildUiContext(), [
                'formMode' => 'create',
                'formAction' => '/rxnTiendasIA/public/mi-empresa/crm/presupuestos',
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
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/presupuestos');
            exit;
        }

        $catalogData = $this->loadCatalogData($empresaId);
        $items = $this->repository->findItemsByPresupuestoId((int) $presupuesto['id'], $empresaId);

        View::render('app/modules/CrmPresupuestos/views/form.php', array_merge($this->buildUiContext(), [
            'formMode' => 'edit',
            'formAction' => '/rxnTiendasIA/public/mi-empresa/crm/presupuestos/' . (int) $presupuesto['id'],
            'presupuesto' => $this->hydrateFormState($presupuesto, $items),
            'catalogs' => $catalogData['catalogs'],
            'catalogSyncWarning' => $catalogData['warning'],
            'errors' => [],
        ]));
    }

    public function update(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $presupuestoActual = $this->repository->findById((int) $id, $empresaId);

        if ($presupuestoActual === null) {
            Flash::set('danger', 'El presupuesto CRM no existe o no pertenece a tu empresa.');
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/presupuestos');
            exit;
        }

        try {
            $payload = $this->validateRequest($_POST, $empresaId, $presupuestoActual);
            $this->repository->update((int) $id, $empresaId, $payload);
            Flash::set('success', 'Presupuesto CRM actualizado correctamente.');
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/presupuestos/' . (int) $id . '/editar');
            exit;
        } catch (ValidationException $e) {
            http_response_code(422);
            $catalogData = $this->loadCatalogData($empresaId);
            View::render('app/modules/CrmPresupuestos/views/form.php', array_merge($this->buildUiContext(), [
                'formMode' => 'edit',
                'formAction' => '/rxnTiendasIA/public/mi-empresa/crm/presupuestos/' . (int) $id,
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
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/presupuestos');
            exit;
        }

        try {
            $data = $presupuestoOriginal;

            unset($data['id'], $data['numero']);

            $data['fecha'] = (new DateTimeImmutable())->format('Y-m-d\TH:i');
            $data['estado'] = 'borrador';

            $itemsOriginales = $this->repository->findItemsByPresupuestoId((int) $id, $empresaId);
            $itemsNuevos = [];
            foreach ($itemsOriginales as $item) {
                unset($item['id'], $item['presupuesto_id']);
                $itemsNuevos[] = $item;
            }
            $data['items'] = $itemsNuevos;

            $nuevoId = $this->repository->create($data);

            Flash::set('success', 'Presupuesto CRM copiado exitosamente.');
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/presupuestos/' . $nuevoId . '/editar');
            exit;
        } catch (Throwable $e) {
            Flash::set('danger', 'Falla al copiar el presupuesto CRM: ' . $e->getMessage());
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/presupuestos/' . (int) $id . '/editar');
            exit;
        }
    }

    public function printPreview(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $presupuesto = $this->repository->findById((int) $id, $empresaId);

        if ($presupuesto === null) {
            Flash::set('danger', 'El presupuesto CRM no existe o no pertenece a tu empresa.');
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/presupuestos');
            exit;
        }

        $items = $this->repository->findItemsByPresupuestoId((int) $id, $empresaId);
        $contextBuilder = new CrmPresupuestoPrintContextBuilder();
        $context = $contextBuilder->build($empresaId, $presupuesto, $items);

        $printRepo = new \App\Modules\PrintForms\PrintFormRepository();
        $renderer = new \App\Modules\PrintForms\PrintFormRenderer();

        try {
            $template = $printRepo->resolveTemplateForDocument($empresaId, 'crm_presupuesto');
            $rendered = $renderer->buildDocument(
                $template['page_config'] ?? [],
                $template['objects'] ?? [],
                $context,
                (string) ($template['background_url'] ?? '')
            );

            \App\Core\View::render('app/modules/PrintForms/views/document_render.php', [
                'title' => 'Presupuesto CRM #' . $presupuesto['numero'],
                'subtitle' => 'Cliente: ' . ($presupuesto['cliente_nombre_snapshot'] ?? 'Sin nombre'),
                'page' => $rendered['page'],
                'renderedObjects' => $rendered['objects'],
                'backPath' => '/rxnTiendasIA/public/mi-empresa/crm/presupuestos/' . (int) $id . '/editar',
                'printPath' => '/rxnTiendasIA/public/mi-empresa/crm/presupuestos/' . (int) $id . '/imprimir?auto=1',
                'autoPrint' => isset($_GET['auto']) && $_GET['auto'] === '1',
            ]);
        } catch (\Throwable $e) {
            Flash::set('danger', 'No se pudo generar la impresion: ' . $e->getMessage());
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/presupuestos/' . (int) $id . '/editar');
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
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/presupuestos');
            exit;
        }

        // Obtener mail del cliente
        $cliente = $this->clienteRepository->findById((int) $presupuesto['cliente_id'], $empresaId);
        $email = $cliente['email'] ?? '';
        if (trim((string)$email) === '') {
            Flash::set('danger', 'El cliente asociado no tiene un correo electrónico configurado.');
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/presupuestos/' . (int) $id . '/editar');
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
                Flash::set('success', 'Email enviado correctamente a ' . htmlspecialchars((string)$email));
            } else {
                Flash::set('danger', 'Error al enviar el correo. Revise su configuración SMTP en Empresa -> Configuración.');
            }
        } catch (\Throwable $e) {
            Flash::set('danger', 'Falla en la generación/envío de documento: ' . $e->getMessage());
        }

        header('Location: /rxnTiendasIA/public/mi-empresa/crm/presupuestos/' . (int) $id . '/editar');
        exit;
    }

    public function syncCatalogs(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $redirectPath = '/rxnTiendasIA/public/mi-empresa/crm/presupuestos';
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $refererPath = (string) parse_url($referer, PHP_URL_PATH);
        $refererQuery = (string) parse_url($referer, PHP_URL_QUERY);
        if ($refererPath !== '' && str_contains($refererPath, '/rxnTiendasIA/public/mi-empresa/crm/presupuestos')) {
            $redirectPath = $refererPath . ($refererQuery !== '' ? '?' . $refererQuery : '');
        }

        try {
            $stats = $this->catalogSyncService->sync($empresaId);
            Flash::set('success', 'Catalogos comerciales CRM sincronizados correctamente.', $stats);
        } catch (Throwable $e) {
            Flash::set('danger', 'No se pudieron sincronizar los catalogos comerciales CRM: ' . $e->getMessage());
        }

        header('Location: ' . $redirectPath);
        exit;
    }

    public function clientSuggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $term = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($term) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $rows = $this->clienteRepository->findSuggestions($empresaId, $term, 'all', 5);
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

        $defaults = $this->loadConfigDefaults($empresaId);
        $payload = [
            'cliente' => [
                'id' => (int) $cliente['id'],
                'nombre' => trim((string) ($cliente['razon_social'] ?? '')),
                'documento' => trim((string) ($cliente['documento'] ?? '')),
            ],
            'deposito' => $this->resolveCatalogSelection($empresaId, 'deposito', $defaults['deposito_codigo'] ?? null, 'Deposito'),
            'condicion' => $this->resolveCatalogSelection($empresaId, 'condicion_venta', $cliente['id_gva01_condicion_venta'] ?? null, 'Condicion'),
            'lista' => $this->resolveCatalogSelection($empresaId, 'lista_precio', $cliente['id_gva10_lista_precios'] ?? ($defaults['lista_codigo'] ?? null), 'Lista'),
            'vendedor' => $this->resolveCatalogSelection($empresaId, 'vendedor', $cliente['id_gva23_vendedor'] ?? null, 'Vendedor'),
            'transporte' => $this->resolveCatalogSelection($empresaId, 'transporte', $cliente['id_gva24_transporte'] ?? null, 'Transporte'),
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
        if (mb_strlen($term) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $rows = ArticuloRepository::forCrm()->findSuggestions($empresaId, $term, 'all', 6);
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

        if ($articuloId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Articulo invalido.', 'data' => []]);
            exit;
        }

        $articulo = $this->repository->findArticleContext($empresaId, $articuloId, $listaCodigo);
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
        ]]);
        exit;
    }

    private function buildUiContext(): array
    {
        return [
            'pageTitle' => 'Presupuestos CRM',
            'dashboardPath' => OperationalAreaService::dashboardPath(OperationalAreaService::AREA_CRM),
            'helpPath' => OperationalAreaService::helpPath(OperationalAreaService::AREA_CRM),
            'basePath' => '/rxnTiendasIA/public/mi-empresa/crm/presupuestos',
            'moduleNotesKey' => 'crm_presupuestos',
            'moduleNotesLabel' => 'Presupuestos CRM',
            'syncCatalogosPath' => '/rxnTiendasIA/public/mi-empresa/crm/presupuestos/catalogos/sincronizar',
        ];
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

    private function loadConfigDefaults(int $empresaId): array
    {
        $config = $this->configRepository->findByEmpresaId($empresaId);

        return [
            'deposito_codigo' => trim((string) ($config?->deposito_codigo ?? '')) ?: null,
            'lista_codigo' => trim((string) ($config?->lista_precio_1 ?? '')) ?: null,
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
        $defaults = $this->loadConfigDefaults($empresaId);

        return [
            'id' => null,
            'numero' => $this->repository->previewNextNumero($empresaId),
            'fecha' => $now->format('Y-m-d\TH:i'),
            'estado' => 'borrador',
            'cliente_id' => '',
            'cliente_nombre' => '',
            'cliente_documento' => '',
            'deposito_codigo' => $defaults['deposito_codigo'] ?? '',
            'condicion_codigo' => '',
            'transporte_codigo' => '',
            'lista_codigo' => $defaults['lista_codigo'] ?? '',
            'vendedor_codigo' => '',
            'subtotal' => 0.0,
            'descuento_total' => 0.0,
            'impuestos_total' => 0.0,
            'total' => 0.0,
            'items' => [],
        ];
    }

    private function hydrateFormState(array $presupuesto, array $items): array
    {
        return [
            'id' => (int) ($presupuesto['id'] ?? 0),
            'numero' => (int) ($presupuesto['numero'] ?? 0),
            'fecha' => $this->formatDateTimeForInput($presupuesto['fecha'] ?? null),
            'estado' => (string) ($presupuesto['estado'] ?? 'borrador'),
            'cliente_id' => (string) ($presupuesto['cliente_id'] ?? ''),
            'cliente_nombre' => (string) ($presupuesto['cliente_nombre_snapshot'] ?? ''),
            'cliente_documento' => (string) ($presupuesto['cliente_documento_snapshot'] ?? ''),
            'deposito_codigo' => (string) ($presupuesto['deposito_codigo'] ?? ''),
            'condicion_codigo' => (string) ($presupuesto['condicion_codigo'] ?? ''),
            'transporte_codigo' => (string) ($presupuesto['transporte_codigo'] ?? ''),
            'lista_codigo' => (string) ($presupuesto['lista_codigo'] ?? ''),
            'vendedor_codigo' => (string) ($presupuesto['vendedor_codigo'] ?? ''),
            'subtotal' => isset($presupuesto['subtotal']) ? (float) $presupuesto['subtotal'] : 0.0,
            'descuento_total' => isset($presupuesto['descuento_total']) ? (float) $presupuesto['descuento_total'] : 0.0,
            'impuestos_total' => isset($presupuesto['impuestos_total']) ? (float) $presupuesto['impuestos_total'] : 0.0,
            'total' => isset($presupuesto['total']) ? (float) $presupuesto['total'] : 0.0,
            'items' => array_map(static function (array $item): array {
                return [
                    'articulo_id' => (string) ($item['articulo_id'] ?? ''),
                    'articulo_codigo' => (string) ($item['articulo_codigo'] ?? ''),
                    'articulo_descripcion' => (string) ($item['articulo_descripcion_snapshot'] ?? ''),
                    'cantidad' => isset($item['cantidad']) ? (float) $item['cantidad'] : 1.0,
                    'precio_unitario' => isset($item['precio_unitario']) ? (float) $item['precio_unitario'] : 0.0,
                    'bonificacion_porcentaje' => isset($item['bonificacion_porcentaje']) ? (float) $item['bonificacion_porcentaje'] : 0.0,
                    'importe_bruto' => isset($item['importe_bruto']) ? (float) $item['importe_bruto'] : 0.0,
                    'importe_neto' => isset($item['importe_neto']) ? (float) $item['importe_neto'] : 0.0,
                    'precio_origen' => (string) ($item['precio_origen'] ?? 'manual'),
                    'lista_codigo_aplicada' => (string) ($item['lista_codigo_aplicada'] ?? ''),
                ];
            }, $items),
        ];
    }

    private function buildFormStateFromPost(array $input, int $empresaId, ?array $presupuestoActual = null): array
    {
        $state = $presupuestoActual !== null
            ? $this->hydrateFormState($presupuestoActual, $this->repository->findItemsByPresupuestoId((int) $presupuestoActual['id'], $empresaId))
            : $this->defaultFormState($empresaId);

        $state['fecha'] = trim((string) ($input['fecha'] ?? $state['fecha']));
        $state['estado'] = $this->normalizeEstado((string) ($input['estado'] ?? $state['estado']));
        $state['cliente_id'] = trim((string) ($input['cliente_id'] ?? $state['cliente_id']));
        $state['cliente_nombre'] = trim((string) ($input['cliente_nombre'] ?? $state['cliente_nombre']));
        $state['cliente_documento'] = trim((string) ($input['cliente_documento'] ?? $state['cliente_documento']));
        $state['deposito_codigo'] = trim((string) ($input['deposito_codigo'] ?? $state['deposito_codigo']));
        $state['condicion_codigo'] = trim((string) ($input['condicion_codigo'] ?? $state['condicion_codigo']));
        $state['transporte_codigo'] = trim((string) ($input['transporte_codigo'] ?? $state['transporte_codigo']));
        $state['lista_codigo'] = trim((string) ($input['lista_codigo'] ?? $state['lista_codigo']));
        $state['vendedor_codigo'] = trim((string) ($input['vendedor_codigo'] ?? $state['vendedor_codigo']));
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
        $fechaInput = trim((string) ($input['fecha'] ?? ''));
        $fecha = $this->parseDateTimeInput($fechaInput);
        if ($fecha === null) {
            $errors['fecha'] = 'Ingresa una fecha valida para el presupuesto.';
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

        $items = $this->normalizePostedItems($input['items'] ?? [], $lista['codigo']);
        if ($items === []) {
            $errors['items'] = 'Agrega al menos un renglon al presupuesto.';
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
            'lista_codigo' => $lista['codigo'],
            'lista_nombre_snapshot' => $lista['descripcion'],
            'lista_id_interno' => $lista['id_interno'],
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

            $items[] = [
                'orden' => count($items) + 1,
                'articulo_id' => $this->nullableInt($row['articulo_id'] ?? null),
                'articulo_codigo' => $codigo,
                'articulo_descripcion' => $descripcion,
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

        $date = new DateTimeImmutable($value);
        return $date->format('Y-m-d\TH:i');
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
