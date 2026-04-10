<?php
declare(strict_types=1);

namespace App\Modules\CrmClientes;

use App\Core\Context;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Shared\Services\OperationalAreaService;

class CrmClienteController extends Controller
{
    private const SEARCH_FIELDS = ['all', 'id', 'codigo_tango', 'razon_social', 'documento', 'email', 'telefono'];

    private CrmClienteRepository $repository;

    public function __construct()
    {
        $this->repository = new CrmClienteRepository();
    }

    public function index(): void
    {
        AuthService::requireLogin();

        $empresaId = (int) Context::getEmpresaId();
        $search = trim((string) ($_GET['search'] ?? ''));
        $field = $this->normalizeSearchField((string) ($_GET['field'] ?? 'all'));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        if (!in_array($limit, [25, 50, 100], true)) {
            $limit = 50;
        }

        $sort = (string) ($_GET['sort'] ?? 'razon_social');
        $dir = strtoupper((string) ($_GET['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $status = (string) ($_GET['status'] ?? 'activos');
        $onlyDeleted = $status === 'papelera';

        $advancedFilters = $this->handleCrudFilters('crm_clientes');

        $totalItems = $this->repository->countAll($empresaId, $search, $field, $onlyDeleted, $advancedFilters);
        $totalPages = max(1, (int) ceil($totalItems / $limit));
        $page = min($page, $totalPages);
        $clientes = $this->repository->findAllPaginated($empresaId, $page, $limit, $search, $field, $sort, $dir, $onlyDeleted, $advancedFilters);

        View::render('app/modules/CrmClientes/views/index.php', array_merge($this->buildUiContext(), [
            'clientes' => $clientes,
            'search' => $search,
            'field' => $field,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'sort' => $sort,
            'dir' => $dir,
            'status' => $status,
        ]));
    }

    public function suggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $search = trim((string) ($_GET['q'] ?? ''));
        $field = $this->normalizeSearchField((string) ($_GET['field'] ?? 'all'));

        if (mb_strlen($search) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $rows = $this->repository->findSuggestions($empresaId, $search, $field, 3);
        $data = array_map(static function (array $row) use ($field): array {
            $razonSocial = trim((string) ($row['razon_social'] ?? 'Cliente'));
            $codigo = trim((string) ($row['codigo_tango'] ?? ''));
            $documento = trim((string) ($row['documento'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $telefono = trim((string) ($row['telefono'] ?? ''));

            $value = match ($field) {
                'id' => (string) ((int) ($row['id'] ?? 0)),
                'codigo_tango' => $codigo,
                'documento' => $documento,
                'email' => $email,
                'telefono' => $telefono,
                default => $razonSocial,
            };

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $razonSocial !== '' ? $razonSocial : 'Cliente',
                'value' => $value !== '' ? $value : $razonSocial,
                'caption' => trim('#' . (int) ($row['id'] ?? 0) . ' | ' . ($codigo !== '' ? $codigo : ($email !== '' ? $email : ($documento !== '' ? $documento : 'Sin referencia')))),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    public function purgar(): void
    {
        AuthService::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->repository->truncate((int) Context::getEmpresaId());
            Flash::set('success', 'Base local de clientes CRM purgada correctamente.');
        }

        $this->redirectToIndex();
    }

    public function eliminarMasivo(): void
    {
        AuthService::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (is_array($ids) && $ids !== []) {
                $this->repository->deleteByIds($ids, (int) Context::getEmpresaId());
                Flash::set('success', 'Clientes CRM seleccionados eliminados de la cache local.');
            }
        }

        $this->redirectToIndex();
    }

    public function eliminar(string $id): void
    {
        AuthService::requireLogin();
        $idInt = (int) $id;
        
        if ($idInt > 0) {
            $this->repository->deleteByIds([$idInt], (int) Context::getEmpresaId());
            Flash::set('success', 'Cliente enviado a la papelera.');
        }
        $this->redirectToIndex();
    }

    public function copy(string $id): void
    {
        AuthService::requireLogin();
        $idInt = (int) $id;
        $empresaId = (int) Context::getEmpresaId();

        try {
            if ($idInt > 0) {
                $this->repository->copy($idInt, $empresaId);
                Flash::set('success', 'Cliente duplicado exitosamente.');
            }
        } catch (\Exception $e) {
            Flash::set('danger', $e->getMessage());
        }

        $this->redirectToIndex();
    }

    public function restore(string $id): void
    {
        AuthService::requireLogin();
        $idInt = (int) $id;
        
        if ($idInt > 0) {
            $this->repository->restoreByIds([$idInt], (int) Context::getEmpresaId());
            Flash::set('success', 'Cliente restaurado.');
        }

        header('Location: /mi-empresa/crm/clientes?status=papelera');
        exit;
    }

    public function forceDelete(string $id): void
    {
        AuthService::requireLogin();
        $idInt = (int) $id;
        
        if ($idInt > 0) {
            $this->repository->forceDeleteByIds([$idInt], (int) Context::getEmpresaId());
            Flash::set('success', 'Cliente eliminado definitivamente.');
        }

        header('Location: /mi-empresa/crm/clientes?status=papelera');
        exit;
    }

    public function restoreMasivo(): void
    {
        AuthService::requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /mi-empresa/crm/clientes?status=papelera');
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $this->repository->restoreByIds(array_map('intval', $ids), (int) Context::getEmpresaId());
            Flash::set('success', 'Se restauraron ' . count($ids) . ' clientes.');
        }

        header('Location: /mi-empresa/crm/clientes?status=papelera');
        exit;
    }

    public function forceDeleteMasivo(): void
    {
        AuthService::requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /mi-empresa/crm/clientes?status=papelera');
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $this->repository->forceDeleteByIds(array_map('intval', $ids), (int) Context::getEmpresaId());
            Flash::set('success', 'Se eliminaron definitivamente ' . count($ids) . ' clientes.');
        }

        header('Location: /mi-empresa/crm/clientes?status=papelera');
        exit;
    }

    public function editar(): void
    {
        AuthService::requireLogin();

        $empresaId = (int) Context::getEmpresaId();
        $id = (int) ($_GET['id'] ?? 0);
        $cliente = $this->repository->findById($id, $empresaId);
        if ($cliente === null) {
            Flash::set('danger', 'El cliente CRM no existe o no pertenece a la empresa activa.');
            $this->redirectToIndex();
        }

        View::render('app/modules/CrmClientes/views/form.php', array_merge($this->buildUiContext(), [
            'cliente' => $cliente,
        ]));
    }

    public function actualizar(): void
    {
        AuthService::requireLogin();

        $empresaId = (int) Context::getEmpresaId();
        $id = (int) ($_GET['id'] ?? 0);
        $cliente = $this->repository->findById($id, $empresaId);
        if ($cliente === null) {
            Flash::set('danger', 'El cliente CRM no existe o no pertenece a la empresa activa.');
            $this->redirectToIndex();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->repository->update($id, $empresaId, [
                'razon_social' => trim((string) ($_POST['razon_social'] ?? '')),
                'documento' => trim((string) ($_POST['documento'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'telefono' => trim((string) ($_POST['telefono'] ?? '')),
                'direccion' => trim((string) ($_POST['direccion'] ?? '')),
                'activo' => isset($_POST['activo']) ? 1 : 0,
            ]);
            Flash::set('success', 'Cliente CRM actualizado correctamente.');
        }

        header('Location: /mi-empresa/crm/clientes/editar?id=' . $id);
        exit;
    }

    public function pushToTango(string $id): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $localId   = (int) $id;

        if ($localId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de cliente inválido.']);
            exit;
        }

        set_time_limit(60);

        try {
            $syncService = new \App\Modules\RxnSync\RxnSyncService();
            $payload = $syncService->pushToTangoByLocalId($empresaId, $localId, 'cliente');
            echo json_encode([
                'success' => true,
                'message' => 'Cliente sincronizado con Tango.',
                'payload' => $payload,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    private function buildUiContext(): array
    {
        return [
            'pageTitle' => 'Clientes CRM',
            'headerTitle' => 'Directorio de Clientes CRM',
            'headerDescription' => 'Cache local sincronizable de clientes Tango para operar el CRM sin depender del lookup remoto en cada pantalla.',
            'dashboardPath' => OperationalAreaService::dashboardPath(OperationalAreaService::AREA_CRM),
            'basePath' => '/mi-empresa/crm/clientes',
            'helpPath' => OperationalAreaService::helpPath(OperationalAreaService::AREA_CRM),
            'moduleNotesKey' => 'crm_clientes',
            'moduleNotesLabel' => 'Clientes CRM',
            'emptyStateTitle' => 'Todavia no hay clientes CRM sincronizados.',
            'emptyStateHint' => 'Usa "Sync Clientes" para traer la base remota de Tango/Connect a la cache local del CRM.',
            'totalBadgeLabel' => 'Total CRM',
            'editTitle' => 'Modificar Cliente CRM',
            'backLabel' => 'Volver a Clientes CRM',
            'syncClientesPath' => '/mi-empresa/crm/sync/clientes',
        ];
    }

    private function normalizeSearchField(string $field): string
    {
        return in_array($field, self::SEARCH_FIELDS, true) ? $field : 'all';
    }

    private function redirectToIndex(): void
    {
        header('Location: /mi-empresa/crm/clientes');
        exit;
    }
}
