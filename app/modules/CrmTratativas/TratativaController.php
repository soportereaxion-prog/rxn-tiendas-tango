<?php
declare(strict_types=1);

namespace App\Modules\CrmTratativas;

use App\Core\Context;
use App\Core\Flash;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\CrmClientes\CrmClienteRepository;
use App\Shared\Services\OperationalAreaService;

class TratativaController extends \App\Core\Controller
{
    private const SEARCH_FIELDS = ['all', 'numero', 'titulo', 'cliente', 'estado', 'usuario'];

    private TratativaRepository $repository;
    private CrmClienteRepository $clienteRepository;

    public function __construct()
    {
        $this->repository = new TratativaRepository();
        $this->clienteRepository = new CrmClienteRepository();
    }

    public function index(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        $advancedFilters = $this->handleCrudFilters('crm_tratativas');

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 25;
        if (!in_array($limit, [25, 50, 100], true)) {
            $limit = 25;
        }

        $search = trim((string) ($_GET['search'] ?? ''));
        $field = $this->normalizeSearchField((string) ($_GET['field'] ?? 'all'));
        $estado = trim((string) ($_GET['estado'] ?? ''));
        $sort = trim((string) ($_GET['sort'] ?? 'created_at'));
        $dir = strtoupper((string) ($_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $totalItems = $this->repository->countAll($empresaId, $search, $field, $estado, $advancedFilters);
        $totalPages = max(1, (int) ceil($totalItems / $limit));
        $page = min($page, $totalPages);
        $tratativas = $this->repository->findAllPaginated($empresaId, $page, $limit, $search, $field, $estado, $sort, $dir, $advancedFilters);

        View::render('app/modules/CrmTratativas/views/index.php', array_merge($this->buildUiContext(), [
            'tratativas' => $tratativas,
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

    public function create(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        View::render('app/modules/CrmTratativas/views/form.php', array_merge($this->buildUiContext(), [
            'formMode' => 'create',
            'formAction' => '/mi-empresa/crm/tratativas',
            'tratativa' => $this->defaultFormState($empresaId),
            'errors' => [],
        ]));
    }

    public function store(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        try {
            $payload = $this->validateRequest($_POST, $empresaId, null);
            $tratativaId = $this->repository->create($payload);
            Flash::set('success', 'Tratativa creada correctamente.');

            header('Location: /mi-empresa/crm/tratativas/' . $tratativaId);
            exit;
        } catch (ValidationException $e) {
            http_response_code(422);
            View::render('app/modules/CrmTratativas/views/form.php', array_merge($this->buildUiContext(), [
                'formMode' => 'create',
                'formAction' => '/mi-empresa/crm/tratativas',
                'tratativa' => $this->buildFormStateFromPost($_POST, $empresaId),
                'errors' => $e->errors(),
            ]));
        }
    }

    public function show(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $tratativa = $this->repository->findById((int) $id, $empresaId);

        if ($tratativa === null) {
            Flash::set('danger', 'La tratativa no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/tratativas');
            exit;
        }

        $pds = $this->repository->findPdsByTratativaId((int) $tratativa['id'], $empresaId);
        $presupuestos = $this->repository->findPresupuestosByTratativaId((int) $tratativa['id'], $empresaId);

        View::render('app/modules/CrmTratativas/views/detalle.php', array_merge($this->buildUiContext(), [
            'tratativa' => $tratativa,
            'pds' => $pds,
            'presupuestos' => $presupuestos,
        ]));
    }

    public function edit(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $tratativa = $this->repository->findById((int) $id, $empresaId);

        if ($tratativa === null) {
            Flash::set('danger', 'La tratativa no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/tratativas');
            exit;
        }

        View::render('app/modules/CrmTratativas/views/form.php', array_merge($this->buildUiContext(), [
            'formMode' => 'edit',
            'formAction' => '/mi-empresa/crm/tratativas/' . (int) $tratativa['id'],
            'tratativa' => $this->hydrateFormState($tratativa),
            'errors' => [],
        ]));
    }

    public function update(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $tratativaActual = $this->repository->findById((int) $id, $empresaId);

        if ($tratativaActual === null) {
            Flash::set('danger', 'La tratativa no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/tratativas');
            exit;
        }

        try {
            $payload = $this->validateRequest($_POST, $empresaId, $tratativaActual);
            $this->repository->update((int) $id, $empresaId, $payload);
            Flash::set('success', 'Tratativa actualizada correctamente.');

            header('Location: /mi-empresa/crm/tratativas/' . (int) $id);
            exit;
        } catch (ValidationException $e) {
            http_response_code(422);
            View::render('app/modules/CrmTratativas/views/form.php', array_merge($this->buildUiContext(), [
                'formMode' => 'edit',
                'formAction' => '/mi-empresa/crm/tratativas/' . (int) $id,
                'tratativa' => $this->buildFormStateFromPost($_POST, $empresaId, $tratativaActual),
                'errors' => $e->errors(),
            ]));
        }
    }

    public function eliminar(string $id): void
    {
        AuthService::requireLogin();
        $idInt = (int) $id;

        if ($idInt > 0) {
            $this->repository->deleteByIds([$idInt], (int) Context::getEmpresaId());
            Flash::set('success', 'Tratativa enviada a la papelera.');
        }

        header('Location: /mi-empresa/crm/tratativas');
        exit;
    }

    public function restore(string $id): void
    {
        AuthService::requireLogin();
        $idInt = (int) $id;

        if ($idInt > 0) {
            $this->repository->restoreByIds([$idInt], (int) Context::getEmpresaId());
            Flash::set('success', 'Tratativa restaurada exitosamente.');
        }

        header('Location: /mi-empresa/crm/tratativas?estado=papelera');
        exit;
    }

    public function forceDelete(string $id): void
    {
        AuthService::requireLogin();
        $idInt = (int) $id;

        if ($idInt > 0) {
            $this->repository->forceDeleteByIds([$idInt], (int) Context::getEmpresaId());
            Flash::set('success', 'Tratativa eliminada permanentemente.');
        }

        header('Location: /mi-empresa/crm/tratativas?estado=papelera');
        exit;
    }

    public function eliminarMasivo(): void
    {
        AuthService::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (is_array($ids) && count($ids) > 0) {
                $count = $this->repository->deleteByIds($ids, (int) Context::getEmpresaId());
                Flash::set('success', "Se enviaron $count tratativas a la papelera.");
            } else {
                Flash::set('warning', 'No se seleccionó ninguna tratativa.');
            }
        }

        header('Location: /mi-empresa/crm/tratativas');
        exit;
    }

    public function restoreMasivo(): void
    {
        AuthService::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (is_array($ids) && count($ids) > 0) {
                $count = $this->repository->restoreByIds($ids, (int) Context::getEmpresaId());
                Flash::set('success', "Se restauraron $count tratativas.");
            } else {
                Flash::set('warning', 'No se seleccionó ninguna tratativa.');
            }
        }

        header('Location: /mi-empresa/crm/tratativas?estado=papelera');
        exit;
    }

    public function forceDeleteMasivo(): void
    {
        AuthService::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $_POST['ids'] ?? [];
            if (is_array($ids) && count($ids) > 0) {
                $count = $this->repository->forceDeleteByIds($ids, (int) Context::getEmpresaId());
                Flash::set('success', "Se eliminaron $count tratativas permanentemente.");
            } else {
                Flash::set('warning', 'No se seleccionó ninguna tratativa.');
            }
        }

        header('Location: /mi-empresa/crm/tratativas?estado=papelera');
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
            $titulo = trim((string) ($row['titulo'] ?? ''));
            $cliente = trim((string) ($row['cliente_nombre'] ?? ''));
            $estadoUi = trim((string) ($row['estado'] ?? 'nueva'));
            $probabilidad = (int) ($row['probabilidad'] ?? 0);

            $value = match ($field) {
                'titulo' => $titulo,
                'cliente' => $cliente,
                'estado' => $estadoUi,
                default => $numero,
            };

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => 'Tratativa #' . $numero,
                'value' => $value !== '' ? $value : $numero,
                'caption' => trim(($titulo !== '' ? $titulo : 'Sin titulo') . ' | ' . ($cliente !== '' ? $cliente : 'Sin cliente') . ' | ' . strtoupper($estadoUi) . ' (' . $probabilidad . '%)'),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data]);
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

        $rows = $this->clienteRepository->findSuggestions($empresaId, $term, 'all', 10);
        $data = array_map(static function (array $row): array {
            $label = trim((string) ($row['razon_social'] ?? ''));
            if ($label === '') {
                $label = trim(((string) ($row['nombre'] ?? '')) . ' ' . ((string) ($row['apellido'] ?? '')));
            }

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

        echo json_encode(['success' => true, 'data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private function validateRequest(array $input, int $empresaId, ?array $tratativaActual): array
    {
        $errors = [];

        $titulo = trim((string) ($input['titulo'] ?? ''));
        $descripcion = trim((string) ($input['descripcion'] ?? ''));
        $estado = trim((string) ($input['estado'] ?? 'nueva'));
        $probabilidad = (int) ($input['probabilidad'] ?? 0);
        $valorEstimado = (float) str_replace(',', '.', (string) ($input['valor_estimado'] ?? '0'));
        $clienteId = (int) ($input['cliente_id'] ?? 0);
        $fechaApertura = trim((string) ($input['fecha_apertura'] ?? ''));
        $fechaCierreEstimado = trim((string) ($input['fecha_cierre_estimado'] ?? ''));
        $fechaCierreReal = trim((string) ($input['fecha_cierre_real'] ?? ''));
        $motivoCierre = trim((string) ($input['motivo_cierre'] ?? ''));

        if ($titulo === '') {
            $errors['titulo'] = 'El título es obligatorio.';
        } elseif (mb_strlen($titulo) > 200) {
            $errors['titulo'] = 'El título no puede superar los 200 caracteres.';
        }

        if (!in_array($estado, TratativaRepository::ESTADOS, true)) {
            $errors['estado'] = 'El estado seleccionado no es válido.';
        }

        if ($probabilidad < 0 || $probabilidad > 100) {
            $errors['probabilidad'] = 'La probabilidad debe estar entre 0 y 100.';
        }

        if ($valorEstimado < 0) {
            $errors['valor_estimado'] = 'El valor estimado no puede ser negativo.';
        }

        // Cliente es opcional, pero si viene un ID tiene que existir en la empresa.
        $cliente = null;
        if ($clienteId > 0) {
            $cliente = $this->clienteRepository->findById($clienteId, $empresaId);
            if ($cliente === null && $tratativaActual !== null && (int) ($tratativaActual['cliente_id'] ?? 0) === $clienteId) {
                $cliente = [
                    'id' => $clienteId,
                    'razon_social' => $tratativaActual['cliente_nombre'] ?? '',
                    'nombre' => $tratativaActual['cliente_nombre'] ?? '',
                    'apellido' => '',
                ];
            }
            if ($cliente === null) {
                $errors['cliente_id'] = 'El cliente seleccionado ya no está disponible.';
            }
        }

        if (in_array($estado, ['ganada', 'perdida'], true) && $motivoCierre === '') {
            $errors['motivo_cierre'] = 'Indicá el motivo del cierre para estados ganada/perdida.';
        }

        $fechaAperturaFinal = $this->parseDateInput($fechaApertura);
        if ($fechaApertura !== '' && $fechaAperturaFinal === null) {
            $errors['fecha_apertura'] = 'La fecha de apertura no tiene un formato válido.';
        }

        $fechaCierreEstimadoFinal = $this->parseDateInput($fechaCierreEstimado);
        if ($fechaCierreEstimado !== '' && $fechaCierreEstimadoFinal === null) {
            $errors['fecha_cierre_estimado'] = 'La fecha de cierre estimado no tiene un formato válido.';
        }

        $fechaCierreRealFinal = $this->parseDateInput($fechaCierreReal);
        if ($fechaCierreReal !== '' && $fechaCierreRealFinal === null) {
            $errors['fecha_cierre_real'] = 'La fecha de cierre real no tiene un formato válido.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $clienteNombre = null;
        if ($cliente !== null) {
            $razon = trim((string) ($cliente['razon_social'] ?? ''));
            $clienteNombre = $razon !== '' ? $razon : trim(((string) ($cliente['nombre'] ?? '')) . ' ' . ((string) ($cliente['apellido'] ?? '')));
        }

        return [
            'empresa_id' => $empresaId,
            'usuario_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            'usuario_nombre' => $_SESSION['user_name'] ?? 'Usuario',
            'cliente_id' => $clienteId > 0 ? $clienteId : null,
            'cliente_nombre' => $clienteNombre,
            'titulo' => $titulo,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'estado' => $estado,
            'probabilidad' => $probabilidad,
            'valor_estimado' => $valorEstimado,
            'fecha_apertura' => $fechaAperturaFinal,
            'fecha_cierre_estimado' => $fechaCierreEstimadoFinal,
            'fecha_cierre_real' => $fechaCierreRealFinal,
            'motivo_cierre' => $motivoCierre !== '' ? $motivoCierre : null,
        ];
    }

    private function buildUiContext(): array
    {
        return [
            'basePath' => '/mi-empresa/crm/tratativas',
            'indexPath' => '/mi-empresa/crm/tratativas',
            'dashboardPath' => OperationalAreaService::dashboardPath(OperationalAreaService::AREA_CRM),
            'helpPath' => OperationalAreaService::helpPath(OperationalAreaService::AREA_CRM),
            'moduleNotesKey' => 'crm_tratativas',
            'moduleNotesLabel' => 'Tratativas CRM',
        ];
    }

    private function normalizeSearchField(string $field): string
    {
        return in_array($field, self::SEARCH_FIELDS, true) ? $field : 'all';
    }

    private function defaultFormState(int $empresaId): array
    {
        $today = date('Y-m-d');

        $clienteId = '';
        $clienteNombre = '';
        if (!empty($_GET['cliente_id'])) {
            $cli = $this->clienteRepository->findById((int) $_GET['cliente_id'], $empresaId);
            if ($cli) {
                $clienteId = (string) $cli['id'];
                $razon = trim((string) ($cli['razon_social'] ?? ''));
                $clienteNombre = $razon !== '' ? $razon : trim(((string) ($cli['nombre'] ?? '')) . ' ' . ((string) ($cli['apellido'] ?? '')));
            }
        }

        return [
            'id' => null,
            'numero' => $this->repository->previewNextNumero($empresaId),
            'titulo' => isset($_GET['titulo']) ? trim((string) $_GET['titulo']) : '',
            'descripcion' => '',
            'cliente_id' => $clienteId,
            'cliente_nombre' => $clienteNombre,
            'estado' => 'nueva',
            'probabilidad' => 0,
            'valor_estimado' => '0.00',
            'fecha_apertura' => $today,
            'fecha_cierre_estimado' => '',
            'fecha_cierre_real' => '',
            'motivo_cierre' => '',
            'usuario_nombre' => $_SESSION['user_name'] ?? 'Usuario',
        ];
    }

    private function hydrateFormState(array $tratativa): array
    {
        return [
            'id' => (int) ($tratativa['id'] ?? 0),
            'numero' => (int) ($tratativa['numero'] ?? 0),
            'titulo' => (string) ($tratativa['titulo'] ?? ''),
            'descripcion' => (string) ($tratativa['descripcion'] ?? ''),
            'cliente_id' => (string) ($tratativa['cliente_id'] ?? ''),
            'cliente_nombre' => (string) ($tratativa['cliente_nombre'] ?? ''),
            'estado' => (string) ($tratativa['estado'] ?? 'nueva'),
            'probabilidad' => (int) ($tratativa['probabilidad'] ?? 0),
            'valor_estimado' => number_format((float) ($tratativa['valor_estimado'] ?? 0), 2, '.', ''),
            'fecha_apertura' => (string) ($tratativa['fecha_apertura'] ?? ''),
            'fecha_cierre_estimado' => (string) ($tratativa['fecha_cierre_estimado'] ?? ''),
            'fecha_cierre_real' => (string) ($tratativa['fecha_cierre_real'] ?? ''),
            'motivo_cierre' => (string) ($tratativa['motivo_cierre'] ?? ''),
            'usuario_nombre' => (string) ($tratativa['usuario_nombre'] ?? 'Usuario'),
        ];
    }

    private function buildFormStateFromPost(array $input, int $empresaId, ?array $tratativaActual = null): array
    {
        $state = $tratativaActual !== null ? $this->hydrateFormState($tratativaActual) : $this->defaultFormState($empresaId);

        $state['titulo'] = trim((string) ($input['titulo'] ?? $state['titulo']));
        $state['descripcion'] = trim((string) ($input['descripcion'] ?? $state['descripcion']));
        $state['cliente_id'] = trim((string) ($input['cliente_id'] ?? $state['cliente_id']));
        $state['cliente_nombre'] = trim((string) ($input['cliente_nombre'] ?? $state['cliente_nombre']));
        $state['estado'] = trim((string) ($input['estado'] ?? $state['estado']));
        $state['probabilidad'] = (int) ($input['probabilidad'] ?? $state['probabilidad']);
        $state['valor_estimado'] = (string) ($input['valor_estimado'] ?? $state['valor_estimado']);
        $state['fecha_apertura'] = trim((string) ($input['fecha_apertura'] ?? $state['fecha_apertura']));
        $state['fecha_cierre_estimado'] = trim((string) ($input['fecha_cierre_estimado'] ?? $state['fecha_cierre_estimado']));
        $state['fecha_cierre_real'] = trim((string) ($input['fecha_cierre_real'] ?? $state['fecha_cierre_real']));
        $state['motivo_cierre'] = trim((string) ($input['motivo_cierre'] ?? $state['motivo_cierre']));

        return $state;
    }

    private function parseDateInput(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'd/m/Y'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
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
