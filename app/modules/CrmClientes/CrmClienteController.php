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
        $totalItems = $this->repository->countAll($empresaId, $search, $field);
        $totalPages = max(1, (int) ceil($totalItems / $limit));
        $page = min($page, $totalPages);
        $clientes = $this->repository->findAllPaginated($empresaId, $page, $limit, $search, $field, $sort, $dir);

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

        header('Location: /rxnTiendasIA/public/mi-empresa/crm/clientes/editar?id=' . $id);
        exit;
    }

    private function buildUiContext(): array
    {
        return [
            'pageTitle' => 'Clientes CRM',
            'headerTitle' => 'Directorio de Clientes CRM',
            'headerDescription' => 'Cache local sincronizable de clientes Tango para operar el CRM sin depender del lookup remoto en cada pantalla.',
            'dashboardPath' => OperationalAreaService::dashboardPath(OperationalAreaService::AREA_CRM),
            'basePath' => '/rxnTiendasIA/public/mi-empresa/crm/clientes',
            'helpPath' => OperationalAreaService::helpPath(OperationalAreaService::AREA_CRM),
            'moduleNotesKey' => 'crm_clientes',
            'moduleNotesLabel' => 'Clientes CRM',
            'emptyStateTitle' => 'Todavia no hay clientes CRM sincronizados.',
            'emptyStateHint' => 'Usa "Sync Clientes" para traer la base remota de Tango/Connect a la cache local del CRM.',
            'totalBadgeLabel' => 'Total CRM',
            'editTitle' => 'Modificar Cliente CRM',
            'backLabel' => 'Volver a Clientes CRM',
            'syncClientesPath' => '/rxnTiendasIA/public/mi-empresa/crm/sync/clientes',
        ];
    }

    private function normalizeSearchField(string $field): string
    {
        return in_array($field, self::SEARCH_FIELDS, true) ? $field : 'all';
    }

    private function redirectToIndex(): void
    {
        header('Location: /rxnTiendasIA/public/mi-empresa/crm/clientes');
        exit;
    }
}
