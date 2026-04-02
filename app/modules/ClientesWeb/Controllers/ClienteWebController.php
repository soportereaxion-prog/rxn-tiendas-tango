<?php
declare(strict_types=1);

namespace App\Modules\ClientesWeb\Controllers;

use App\Core\View;
use App\Modules\ClientesWeb\ClienteWebRepository;
use App\Modules\ClientesWeb\Services\ClienteTangoLookupService;
use App\Core\Database;
use App\Modules\Auth\AuthService;
use App\Core\Context;
use Exception;

class ClienteWebController
{
    private const SEARCH_FIELDS = ['all', 'id', 'nombre', 'email', 'documento', 'codigo_tango'];

    public function index(): void
    {
        AuthService::requireLogin();
        
        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int)Context::getEmpresaId();
        
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 20;
        $search = trim($_GET['search'] ?? '');
        $field = $this->normalizeSearchField($_GET['field'] ?? 'all');
        $sort = $_GET['sort'] ?? 'id';
        $dir = $_GET['dir'] ?? 'DESC';
        $status = $_GET['status'] ?? 'activos';

        $clientes = $repository->findAllPaginated($empresaId, $page, $limit, $search, $field, $sort, $dir, $status);
        $total = $repository->countAll($empresaId, $search, $field, $status);
        $totalPages = ceil($total / $limit) ?: 1;

        View::render('app/modules/ClientesWeb/views/index.php', array_merge($ui, [
            'clientes' => $clientes,
            'page' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'field' => $field,
            'sort' => $sort,
            'dir' => $dir,
            'status' => $status
        ]));
    }

    public function suggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $empresaId = (int) Context::getEmpresaId();
        $term = trim($_GET['q'] ?? '');
        $field = $this->normalizeSearchField($_GET['field'] ?? 'all');

        if (mb_strlen($term) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $rows = $repository->findSuggestions($empresaId, $term, $field, 3);
        $data = array_map(function (array $row) use ($field): array {
            $label = trim(((string) ($row['nombre'] ?? '')) . ' ' . ((string) ($row['apellido'] ?? '')));
            $email = trim((string) ($row['email'] ?? ''));
            $documento = trim((string) ($row['documento'] ?? ''));
            $codigoTango = trim((string) ($row['codigo_tango'] ?? ''));
            $value = match ($field) {
                'id' => (string) ((int) ($row['id'] ?? 0)),
                'email' => $email,
                'documento' => $documento,
                'codigo_tango' => $codigoTango,
                default => ($label !== '' ? $label : $email),
            };

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $label !== '' ? $label : 'Cliente',
                'value' => $value !== '' ? $value : ($label !== '' ? $label : $email),
                'caption' => '#'. (int) ($row['id'] ?? 0) . ' | ' . (($email !== '' ? $email : ($documento !== '' ? $documento : 'Sin referencia'))),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    public function edit(int $id): void
    {
        AuthService::requireLogin();
        
        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int)Context::getEmpresaId();
        
        $cliente = $repository->findById($id, $empresaId);

        if (!$cliente) {
            $_SESSION['flash_error'] = "Cliente no encontrado.";
            header('Location: ' . $ui['basePath']);
            exit;
        }

        View::render('app/modules/ClientesWeb/views/edit.php', array_merge($ui, [
            'cliente' => $cliente
        ]));
    }

    public function update(int $id): void
    {
        AuthService::requireLogin();
        
        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int)Context::getEmpresaId();
        
        $cliente = $repository->findById($id, $empresaId);

        if (!$cliente) {
            header('Location: ' . $ui['basePath']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'nombre' => trim($_POST['nombre'] ?? ''),
                'apellido' => trim($_POST['apellido'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'telefono' => trim($_POST['telefono'] ?? ''),
                'documento' => trim($_POST['documento'] ?? ''),
                'razon_social' => trim($_POST['razon_social'] ?? ''),
                'direccion' => trim($_POST['direccion'] ?? ''),
                'localidad' => trim($_POST['localidad'] ?? ''),
                'provincia' => trim($_POST['provincia'] ?? ''),
                'codigo_postal' => trim($_POST['codigo_postal'] ?? ''),
                'codigo_tango' => trim($_POST['codigo_tango'] ?? ''),
                'activo' => isset($_POST['activo']) ? 1 : 0
            ];
            $relationOverrides = $this->extractRelationOverrides($_POST);
            $remoteSyncRequested = isset($_POST['tango_remote_sync_requested']) && $_POST['tango_remote_sync_requested'] === '1';

            $repository->update($id, $data);

            $selectedTangoId = trim($_POST['tango_selected_id_gva14'] ?? '');
            $codigoTangoNuevo = $data['codigo_tango'];
            $codigoTangoAnterior = trim((string) ($cliente['codigo_tango'] ?? ''));

            if ($codigoTangoNuevo === '') {
                $repository->clearTangoData($id);
            } elseif ($remoteSyncRequested && $selectedTangoId !== '') {
                try {
                    $lookupService = $this->buildLookupService($empresaId);
                    $tangoData = $lookupService->findById($selectedTangoId);

                    if ($tangoData) {
                        $tangoData = $this->applyRelationOverrides($tangoData, $relationOverrides);
                        $repository->updateTangoData($id, $tangoData);
                    } else {
                        $repository->clearTangoData($id, $codigoTangoNuevo);
                    }
                } catch (Exception $e) {
                    $repository->clearTangoData($id, $codigoTangoNuevo);
                    $_SESSION['flash_error'] = "Se guardaron los cambios locales, pero no se pudo resolver el cliente Tango seleccionado: " . $e->getMessage();
                    header("Location: {$ui['basePath']}/$id/editar");
                    exit;
                }
            } elseif ($codigoTangoNuevo !== $codigoTangoAnterior) {
                $repository->clearTangoData($id, $codigoTangoNuevo);
            } elseif ($cliente['id_gva14_tango'] ?? null) {
                $repository->updateRelacionOverrides($id, $relationOverrides);
            }

            $_SESSION['flash_success'] = "Cliente web actualizado correctamente.";
            header("Location: {$ui['basePath']}/$id/editar");
            exit;
        }
    }

    public function eliminar(int $id): void
    {
        AuthService::requireLogin();
        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int)Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $repository->softDelete($id, $empresaId);
            $_SESSION['flash_success'] = "Cliente enviado a la papelera.";
        }
        header('Location: ' . $ui['basePath']);
        exit;
    }

    public function restore(int $id): void
    {
        AuthService::requireLogin();
        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int)Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $repository->restore($id, $empresaId);
            $_SESSION['flash_success'] = "Cliente restaurado correctamente.";
        }
        header('Location: ' . $ui['basePath'] . '?status=papelera');
        exit;
    }

    public function forceDelete(int $id): void
    {
        AuthService::requireLogin();
        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int)Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $repository->forceDelete($id, $empresaId);
            $_SESSION['flash_success'] = "Cliente eliminado definitivamente.";
        }
        header('Location: ' . $ui['basePath'] . '?status=papelera');
        exit;
    }

    public function eliminarMasivo(): void
    {
        AuthService::requireLogin();
        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int)Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
            if (!empty($ids)) {
                $repository->softDeleteBulk($ids, $empresaId);
                $_SESSION['flash_success'] = count($ids) . " clientes enviados a la papelera.";
            } else {
                $_SESSION['flash_error'] = "No se seleccionaron clientes.";
            }
        }
        header('Location: ' . $ui['basePath']);
        exit;
    }

    public function restoreMasivo(): void
    {
        AuthService::requireLogin();
        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int)Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
            if (!empty($ids)) {
                $repository->restoreBulk($ids, $empresaId);
                $_SESSION['flash_success'] = count($ids) . " clientes restaurados.";
            } else {
                $_SESSION['flash_error'] = "No se seleccionaron clientes.";
            }
        }
        header('Location: ' . $ui['basePath'] . '?status=papelera');
        exit;
    }

    public function forceDeleteMasivo(): void
    {
        AuthService::requireLogin();
        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int)Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
            if (!empty($ids)) {
                $repository->forceDeleteBulk($ids, $empresaId);
                $_SESSION['flash_success'] = count($ids) . " clientes eliminados definitivamente.";
            } else {
                $_SESSION['flash_error'] = "No se seleccionaron clientes.";
            }
        }
        header('Location: ' . $ui['basePath'] . '?status=papelera');
        exit;
    }

    public function buscarTango(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $term = trim($_GET['q'] ?? '');

        if (mb_strlen($term) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        try {
            $lookupService = $this->buildLookupService($empresaId);
            $results = $lookupService->search($term, 10);

            echo json_encode(['success' => true, 'data' => $results]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
        }

        exit;
    }

    public function obtenerMetadataTango(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $clienteIdGva14 = trim($_GET['cliente_id_gva14'] ?? '');

        try {
            $lookupService = $this->buildLookupService($empresaId);
            $results = $lookupService->getRelacionCatalogs();

            if ($clienteIdGva14 !== '') {
                $clienteTango = $lookupService->findById($clienteIdGva14);
                $results['defaults'] = [
                    'gva01' => $clienteTango['id_gva01_condicion_venta'] ?? null,
                    'gva10' => $clienteTango['id_gva10_lista_precios'] ?? null,
                    'gva23' => $clienteTango['id_gva23_vendedor'] ?? null,
                    'gva24' => $clienteTango['id_gva24_transporte'] ?? null,
                ];
            }

            echo json_encode(['success' => true, 'data' => $results]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
        }

        exit;
    }

    public function validarTango(int $id): void
    {
        AuthService::requireLogin();
        
        $area = $this->resolveArea();
        $repository = $this->resolveRepository($area);
        $ui = $this->buildUiContext($area);
        $empresaId = (int)Context::getEmpresaId();
        
        $cliente = $repository->findById($id, $empresaId);

        if (!$cliente) {
            $_SESSION['flash_error'] = "Cliente no encontrado.";
            header('Location: ' . $ui['basePath']);
            exit;
        }

        $codigoTango = trim($_POST['codigo_tango'] ?? $cliente['codigo_tango'] ?? '');
        
        if (empty($codigoTango)) {
            $_SESSION['flash_error'] = "Primero debes guardar un Código Tango antes de validar.";
            header("Location: {$ui['basePath']}/$id/editar");
            exit;
        }

        try {
            $lookupService = $this->buildLookupService($empresaId);
            $tangoData = $lookupService->findByCodigo($codigoTango);

            if (!$tangoData) {
                $_SESSION['flash_error'] = "El cliente con código '{$codigoTango}' NO fue encontrado en Tango.";
                
                // Si el operario intentó validar un nuevo código en vuelo sin guardar, lo guardamos para que no se pierda el input
                if ($cliente['codigo_tango'] !== $codigoTango) {
                    $repository->clearTangoData($id, $codigoTango);
                }
            } else {
                $repository->updateTangoData($id, $tangoData);
                $_SESSION['flash_success'] = "Cliente resuelto correctamente en Tango. (ID_GVA14: {$tangoData['id_gva14_tango']})";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error validando en Tango: " . $e->getMessage();
        }

        header("Location: {$ui['basePath']}/$id/editar");
        exit;
    }

    private function buildLookupService(int $empresaId): ClienteTangoLookupService
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT tango_connect_token, tango_connect_company_id, tango_connect_key FROM empresa_config WHERE empresa_id = :emp_id LIMIT 1");
        $stmt->execute(['emp_id' => $empresaId]);
        $config = $stmt->fetch();

        if (!$config || empty($config['tango_connect_token']) || empty($config['tango_connect_key'])) {
            throw new Exception("Configuración de Tango incompleta para la empresa.");
        }

        $tangoKeySanitized = str_replace('/', '-', $config['tango_connect_key']);
        $apiUrl = rtrim(sprintf("https://%s.connect.axoft.com/Api", $tangoKeySanitized), '/');

        return new ClienteTangoLookupService($apiUrl, $config['tango_connect_token'], (string) $config['tango_connect_company_id']);
    }

    private function extractRelationOverrides(array $payload): array
    {
        return [
            'id_gva01_condicion_venta' => trim($payload['id_gva01_condicion_venta'] ?? ''),
            'id_gva10_lista_precios' => trim($payload['id_gva10_lista_precios'] ?? ''),
            'id_gva23_vendedor' => trim($payload['id_gva23_vendedor'] ?? ''),
            'id_gva24_transporte' => trim($payload['id_gva24_transporte'] ?? ''),
            'id_gva01_tango' => trim($payload['id_gva01_tango'] ?? ''),
            'id_gva10_tango' => trim($payload['id_gva10_tango'] ?? ''),
            'id_gva23_tango' => trim($payload['id_gva23_tango'] ?? ''),
            'id_gva24_tango' => trim($payload['id_gva24_tango'] ?? ''),
        ];
    }

    private function applyRelationOverrides(array $tangoData, array $relationOverrides): array
    {
        $mapping = [
            'id_gva01_condicion_venta' => 'id_gva01_tango',
            'id_gva10_lista_precios' => 'id_gva10_tango',
            'id_gva23_vendedor' => 'id_gva23_tango',
            'id_gva24_transporte' => 'id_gva24_tango',
        ];

        foreach ($mapping as $codigoKey => $internoKey) {
            if (array_key_exists($codigoKey, $relationOverrides)) {
                $tangoData[$codigoKey] = $relationOverrides[$codigoKey] !== '' ? $relationOverrides[$codigoKey] : null;
            }

            if (array_key_exists($internoKey, $relationOverrides)) {
                $tangoData[$internoKey] = $relationOverrides[$internoKey] !== '' ? $relationOverrides[$internoKey] : null;
            }
        }

        return $tangoData;
    }

    private function hasRelationOverridePayload(array $relationOverrides): bool
    {
        foreach ($relationOverrides as $value) {
            if ($value !== '') {
                return true;
            }
        }

        return false;
    }

    public function enviarPendientes(int $id): void
    {
        AuthService::requireLogin();
        $area = $this->resolveArea();
        $ui = $this->buildUiContext($area);
        $empresaId = (int)Context::getEmpresaId();
        
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id FROM pedidos_web WHERE cliente_web_id = :cid AND empresa_id = :eid AND estado_tango != 'enviado_tango' ORDER BY id ASC LIMIT 1");
        $stmt->execute(['cid' => $id, 'eid' => $empresaId]);
        $pedidoId = $stmt->fetchColumn();

        if (!$pedidoId) {
            $_SESSION['flash_error'] = "No hay pedidos pendientes o con error para intentar enviar.";
            header("Location: {$ui['basePath']}/$id/editar");
            exit;
        }

        // Redirigir al reprocesar del primer pedido encontrado. 
        // Así reaprovechamos todo el pipeline de validaciones, mappers y UI de Pedidos.
        header("Location: /rxnTiendasIA/public/mi-empresa/pedidos/{$pedidoId}/reprocesar");
        exit;
    }

    private function normalizeSearchField(string $field): string
    {
        return in_array($field, self::SEARCH_FIELDS, true) ? $field : 'all';
    }

    private function resolveArea(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        return str_contains($uri, '/mi-empresa/crm/') ? 'crm' : 'tiendas';
    }

    private function resolveRepository(string $area): ClienteWebRepository
    {
        return $area === 'crm' ? ClienteWebRepository::forCrm() : new ClienteWebRepository();
    }

    private function buildUiContext(string $area): array
    {
        if ($area === 'crm') {
            return [
                'pageTitle' => 'Clientes CRM',
                'headerTitle' => 'Clientes CRM',
                'headerDescription' => 'Directorio de Clientes CRM y vinculacion comercial.',
                'dashboardPath' => '/rxnTiendasIA/public/mi-empresa/crm/dashboard',
                'basePath' => '/rxnTiendasIA/public/mi-empresa/crm/clientes',
                'helpPath' => '/rxnTiendasIA/public/mi-empresa/crm/ayuda',
                'moduleNotesKey' => 'crm_clientes',
                'moduleNotesLabel' => 'Clientes CRM',
                'emptyStateTitle' => 'No hay clientes registrados en CRM.',
                'totalBadgeLabel' => 'Total Clientes CRM',
                'editTitle' => 'Modificar Cliente CRM',
                'backLabel' => 'Volver a Clientes CRM',
                'isCrm' => true,
            ];
        }

        return [
            'pageTitle' => 'Clientes Web',
            'headerTitle' => 'Clientes Web',
            'headerDescription' => 'Gestión de Clientes y Vínculo Comercial Tango.',
            'dashboardPath' => '/rxnTiendasIA/public/mi-empresa/dashboard',
            'basePath' => '/rxnTiendasIA/public/mi-empresa/clientes',
            'helpPath' => '/rxnTiendasIA/public/mi-empresa/ayuda',
            'moduleNotesKey' => 'clientes_web',
            'moduleNotesLabel' => 'Clientes Web',
            'emptyStateTitle' => 'No hay clientes web registrados.',
            'totalBadgeLabel' => 'Total Clientes Web',
            'editTitle' => 'Editar Cliente Web',
            'backLabel' => 'Volver al Listado',
            'isCrm' => false,
        ];
    }
}
