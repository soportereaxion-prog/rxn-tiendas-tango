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
    private const SEARCH_FIELDS = ['all', 'numero', 'cliente', 'solicito', 'articulo', 'clasificacion', 'estado'];

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
            'formAction' => '/rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio',
            'pedido' => $this->defaultFormState($empresaId),
            'errors' => [],
        ]));
    }

    public function store(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        try {
            $payload = $this->validateRequest($_POST, $empresaId, null);
            $pedidoId = $this->repository->create($payload);
            
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
            
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio/' . $pedidoId . '/editar');
            exit;
        } catch (ValidationException $e) {
            http_response_code(422);
            View::render('app/modules/CrmPedidosServicio/views/form.php', array_merge($this->buildUiContext(), [
                'formMode' => 'create',
                'formAction' => '/rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio',
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
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio');
            exit;
        }

        View::render('app/modules/CrmPedidosServicio/views/form.php', array_merge($this->buildUiContext(), [
            'formMode' => 'edit',
            'formAction' => '/rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio/' . (int) $pedido['id'],
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
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio');
            exit;
        }

        try {
            $payload = $this->validateRequest($_POST, $empresaId, $pedidoActual);
            $this->repository->update((int) $id, $empresaId, $payload);
            
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
            
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio/' . (int) $id . '/editar');
            exit;
        } catch (ValidationException $e) {
            http_response_code(422);
            View::render('app/modules/CrmPedidosServicio/views/form.php', array_merge($this->buildUiContext(), [
                'formMode' => 'edit',
                'formAction' => '/rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio/' . (int) $id,
                'pedido' => $this->buildFormStateFromPost($_POST, $empresaId, $pedidoActual),
                'errors' => $e->errors(),
            ]));
        }
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

        $rows = $this->repository->findClientSuggestions($empresaId, $term, 5);
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

        echo json_encode(['success' => true, 'data' => $data]);
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

        $rows = $this->repository->findArticleSuggestions($empresaId, $term, 5);
        $data = array_map(static function (array $row): array {
            $nombre = trim((string) ($row['nombre'] ?? ''));
            $codigo = trim((string) ($row['codigo_externo'] ?? ''));

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $nombre !== '' ? $nombre : 'Articulo',
                'value' => $nombre !== '' ? $nombre : 'Articulo',
                'caption' => trim('#' . (int) ($row['id'] ?? 0) . ' | ' . ($codigo !== '' ? $codigo : 'Sin codigo')),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    public function classificationSuggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $term = trim((string) ($_GET['q'] ?? ''));
        $items = $this->repository->findClasificacionSuggestions($empresaId, $term, 6);
        $data = array_map(static fn (string $code): array => [
            'id' => $code,
            'label' => $code,
            'value' => $code,
            'caption' => 'Clasificacion operativa',
        ], $items);

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    private function validateRequest(array $input, int $empresaId, ?array $pedidoActual): array
    {
        $errors = [];
        $fechaInicioInput = trim((string) ($input['fecha_inicio'] ?? ''));
        $fechaFinalizadoInput = trim((string) ($input['fecha_finalizado'] ?? ''));
        $solicito = trim((string) ($input['solicito'] ?? ''));
        $nroPedido = trim((string) ($input['nro_pedido'] ?? ''));
        $clasificacion = strtoupper(trim((string) ($input['clasificacion_codigo'] ?? '')));
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
            $errors['fecha_finalizado'] = 'La fecha de finalización es obligatoria para guardar.';
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
            'descuento_segundos' => $descuentoSegundos,
            'diagnostico' => $diagnostico !== '' ? $diagnostico : null,
            'motivo_descuento' => $motivo_descuento !== '' ? $motivo_descuento : null,
            'duracion_bruta_segundos' => $duracionBruta,
            'duracion_neta_segundos' => $duracionNeta,
        ];
    }

    private function buildUiContext(): array
    {
        return [
            'basePath' => '/rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio',
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

        return [
            'id' => null,
            'numero' => $this->repository->previewNextNumero($empresaId),
            'fecha_inicio' => $now->format('Y-m-d\TH:i:s'),
            'fecha_finalizado' => '',
            'cliente_id' => '',
            'cliente_nombre' => '',
            'solicito' => '',
            'nro_pedido' => '',
            'articulo_id' => '',
            'articulo_nombre' => '',
            'clasificacion_codigo' => '',
            'descuento' => '00:00:00',
            'diagnostico' => '',
            'motivo_descuento' => '',
            'duracion_bruta_segundos' => null,
            'duracion_neta_segundos' => null,
            'duracion_bruta_hhmmss' => '--:--:--',
            'duracion_neta_hhmmss' => '--:--:--',
            'estado_ui' => 'abierto',
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
            'descuento' => $this->formatDuration((int) ($pedido['descuento_segundos'] ?? 0)),
            'diagnostico' => (string) ($pedido['diagnostico'] ?? ''),
            'motivo_descuento' => (string) ($pedido['motivo_descuento'] ?? ''),
            'duracion_bruta_segundos' => isset($pedido['duracion_bruta_segundos']) ? (int) $pedido['duracion_bruta_segundos'] : null,
            'duracion_neta_segundos' => isset($pedido['duracion_neta_segundos']) ? (int) $pedido['duracion_neta_segundos'] : null,
            'duracion_bruta_hhmmss' => isset($pedido['duracion_bruta_segundos']) ? $this->formatDuration((int) $pedido['duracion_bruta_segundos']) : '--:--:--',
            'duracion_neta_hhmmss' => isset($pedido['duracion_neta_segundos']) ? $this->formatDuration((int) $pedido['duracion_neta_segundos']) : '--:--:--',
            'estado_ui' => empty($pedido['fecha_finalizado']) ? 'abierto' : 'finalizado',
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
