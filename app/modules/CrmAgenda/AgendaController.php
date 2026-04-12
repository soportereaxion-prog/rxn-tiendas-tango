<?php
declare(strict_types=1);

namespace App\Modules\CrmAgenda;

use App\Core\Context;
use App\Core\Flash;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Shared\Services\OperationalAreaService;
use DateTimeImmutable;

class AgendaController extends \App\Core\Controller
{
    private AgendaRepository $repository;
    private GoogleOAuthService $oauthService;

    public function __construct()
    {
        $this->repository = new AgendaRepository();
        $this->oauthService = new GoogleOAuthService();
    }

    public function index(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $usuarioId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

        $empresaConfig = $this->oauthService->loadEmpresaConfig($empresaId);
        $authMode = $empresaConfig['auth_mode'];
        $authConfigured = $this->oauthService->isConfigured($empresaId);
        $missingFields = $authConfigured ? [] : $this->oauthService->getMissingConfigFields($empresaId);
        $authActive = $authConfigured ? $this->oauthService->getActiveAuth($empresaId, $usuarioId) : null;

        // En modo 'ambos', mostramos tambien el auth empresa-wide por separado
        $authEmpresa = null;
        if ($authMode === 'ambos' && $authConfigured) {
            $authEmpresa = $this->oauthService->findAuth($empresaId, null);
        }

        View::render('app/modules/CrmAgenda/views/index.php', array_merge($this->buildUiContext(), [
            'authMode' => $authMode,
            'authConfigured' => $authConfigured,
            'missingFields' => $missingFields,
            'authActive' => $authActive,
            'authEmpresa' => $authEmpresa,
            'empresaConfig' => $empresaConfig,
        ]));
    }

    /**
     * POST /mi-empresa/crm/agenda/google/config
     * Guarda las credenciales OAuth de Google Calendar para la empresa.
     */
    public function googleConfig(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /mi-empresa/crm/agenda');
            exit;
        }

        $clientId = trim((string) ($_POST['google_oauth_client_id'] ?? ''));
        $clientSecret = trim((string) ($_POST['google_oauth_client_secret'] ?? ''));
        $redirectUri = trim((string) ($_POST['google_oauth_redirect_uri'] ?? ''));
        $authMode = trim((string) ($_POST['agenda_google_auth_mode'] ?? 'usuario'));

        $this->oauthService->saveEmpresaConfig($empresaId, $clientId, $clientSecret, $redirectUri, $authMode);

        Flash::set('success', 'Configuración de Google Calendar guardada correctamente.');
        header('Location: /mi-empresa/crm/agenda');
        exit;
    }

    /**
     * Endpoint JSON para FullCalendar events feed.
     * FullCalendar llama GET /events?start=...&end=... con ISO 8601.
     */
    public function eventsFeed(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = (int) Context::getEmpresaId();
        $startParam = trim((string) ($_GET['start'] ?? ''));
        $endParam = trim((string) ($_GET['end'] ?? ''));

        $start = $this->normalizeDateParam($startParam) ?? (new DateTimeImmutable('-1 month'))->format('Y-m-d H:i:s');
        $end = $this->normalizeDateParam($endParam) ?? (new DateTimeImmutable('+3 months'))->format('Y-m-d H:i:s');

        $usuarioFilter = isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '' ? (int) $_GET['usuario_id'] : null;
        $origenesFilter = isset($_GET['origenes']) && is_array($_GET['origenes']) ? $_GET['origenes'] : null;

        $rows = $this->repository->findInRange($empresaId, $start, $end, $usuarioFilter, $origenesFilter);

        $events = array_map(static function (array $row): array {
            $allDay = (bool) $row['all_day'];
            return [
                'id' => (int) $row['id'],
                'title' => (string) $row['titulo'],
                'start' => $allDay ? substr((string) $row['inicio'], 0, 10) : (string) str_replace(' ', 'T', (string) $row['inicio']),
                'end' => $allDay ? substr((string) $row['fin'], 0, 10) : (string) str_replace(' ', 'T', (string) $row['fin']),
                'allDay' => $allDay,
                'backgroundColor' => (string) ($row['color'] ?? '#6c757d'),
                'borderColor' => (string) ($row['color'] ?? '#6c757d'),
                'extendedProps' => [
                    'descripcion' => (string) ($row['descripcion'] ?? ''),
                    'ubicacion' => (string) ($row['ubicacion'] ?? ''),
                    'usuario_nombre' => (string) ($row['usuario_nombre'] ?? ''),
                    'origen_tipo' => (string) ($row['origen_tipo'] ?? 'manual'),
                    'origen_id' => (int) ($row['origen_id'] ?? 0),
                    'estado' => (string) ($row['estado'] ?? 'programado'),
                    'sync' => !empty($row['google_event_id']) ? 'synced' : 'local',
                ],
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => $events], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function create(): void
    {
        AuthService::requireLogin();

        $now = new DateTimeImmutable();
        $defaults = [
            'id' => null,
            'titulo' => '',
            'descripcion' => '',
            'ubicacion' => '',
            'inicio' => $now->format('Y-m-d\TH:i'),
            'fin' => $now->modify('+1 hour')->format('Y-m-d\TH:i'),
            'all_day' => 0,
            'color' => AgendaRepository::defaultColorFor('manual'),
        ];

        // Pre-carga desde FullCalendar click: ?start=...&end=...
        if (!empty($_GET['start'])) {
            $defaults['inicio'] = trim((string) $_GET['start']);
        }
        if (!empty($_GET['end'])) {
            $defaults['fin'] = trim((string) $_GET['end']);
        }

        View::render('app/modules/CrmAgenda/views/form.php', array_merge($this->buildUiContext(), [
            'formMode' => 'create',
            'formAction' => '/mi-empresa/crm/agenda',
            'evento' => $defaults,
            'errors' => [],
        ]));
    }

    public function store(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        try {
            $payload = $this->validateRequest($_POST, $empresaId, null);
            $id = $this->repository->create($payload);

            // Push a Google si corresponde
            $payload['id'] = $id;
            $this->maybePushToGoogle($payload);

            Flash::set('success', 'Evento creado correctamente.');
            header('Location: /mi-empresa/crm/agenda');
            exit;
        } catch (ValidationException $e) {
            http_response_code(422);
            View::render('app/modules/CrmAgenda/views/form.php', array_merge($this->buildUiContext(), [
                'formMode' => 'create',
                'formAction' => '/mi-empresa/crm/agenda',
                'evento' => $this->rebuildFormStateFromPost($_POST),
                'errors' => $e->errors(),
            ]));
        }
    }

    public function edit(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $evento = $this->repository->findById((int) $id, $empresaId);

        if ($evento === null) {
            Flash::set('danger', 'El evento no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/agenda');
            exit;
        }

        View::render('app/modules/CrmAgenda/views/form.php', array_merge($this->buildUiContext(), [
            'formMode' => 'edit',
            'formAction' => '/mi-empresa/crm/agenda/' . (int) $evento['id'],
            'evento' => $this->hydrateFormState($evento),
            'errors' => [],
        ]));
    }

    public function update(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $actual = $this->repository->findById((int) $id, $empresaId);

        if ($actual === null) {
            Flash::set('danger', 'El evento no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/agenda');
            exit;
        }

        // Los eventos proyectados desde otros modulos no se editan desde la agenda
        // (hay que editarlos en su modulo de origen).
        if (($actual['origen_tipo'] ?? 'manual') !== 'manual') {
            Flash::set('warning', 'Este evento proviene del módulo de ' . $actual['origen_tipo'] . '. Editalo desde allí.');
            header('Location: /mi-empresa/crm/agenda');
            exit;
        }

        try {
            $payload = $this->validateRequest($_POST, $empresaId, $actual);
            $payload['google_event_id'] = $actual['google_event_id'] ?? null;
            $payload['google_calendar_id'] = $actual['google_calendar_id'] ?? null;
            $this->repository->update((int) $id, $empresaId, $payload);

            $payload['id'] = (int) $id;
            $this->maybePushToGoogle($payload);

            Flash::set('success', 'Evento actualizado correctamente.');
            header('Location: /mi-empresa/crm/agenda');
            exit;
        } catch (ValidationException $e) {
            http_response_code(422);
            View::render('app/modules/CrmAgenda/views/form.php', array_merge($this->buildUiContext(), [
                'formMode' => 'edit',
                'formAction' => '/mi-empresa/crm/agenda/' . (int) $id,
                'evento' => $this->rebuildFormStateFromPost($_POST, $actual),
                'errors' => $e->errors(),
            ]));
        }
    }

    public function eliminar(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $evento = $this->repository->findById((int) $id, $empresaId);

        if ($evento === null) {
            Flash::set('danger', 'El evento no existe o no pertenece a tu empresa.');
            header('Location: /mi-empresa/crm/agenda');
            exit;
        }

        if (($evento['origen_tipo'] ?? 'manual') !== 'manual') {
            Flash::set('warning', 'Este evento proviene del módulo de ' . $evento['origen_tipo'] . '. Eliminalo desde allí.');
            header('Location: /mi-empresa/crm/agenda');
            exit;
        }

        // Borrado remoto best-effort
        if (!empty($evento['google_event_id'])) {
            try {
                (new GoogleCalendarSyncService($this->repository, $this->oauthService))->deleteRemote($evento);
            } catch (\Throwable) {}
        }

        $this->repository->softDeleteById((int) $id, $empresaId);
        Flash::set('success', 'Evento eliminado.');
        header('Location: /mi-empresa/crm/agenda');
        exit;
    }

    // ---- Google OAuth endpoints ----

    public function googleConnect(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $usuarioId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

        // Guard temprano: si falta configuracion de OAuth, mensaje amigable en vez de stacktrace
        if (!$this->oauthService->isConfigured($empresaId)) {
            $missing = implode(', ', $this->oauthService->getMissingConfigFields($empresaId));
            Flash::set('warning', 'Configuración de Google OAuth pendiente. Faltan: ' . $missing . '. Completá los datos en Configuración CRM o en el panel de esta Agenda.');
            header('Location: /mi-empresa/crm/agenda');
            exit;
        }

        $mode = $this->oauthService->resolveAgendaMode($empresaId);

        try {
            $url = $this->oauthService->getAuthUrl($empresaId, $mode === 'empresa' ? null : $usuarioId, $mode);
            header('Location: ' . $url);
            exit;
        } catch (\Throwable $e) {
            Flash::set('danger', 'No se pudo iniciar el flujo OAuth con Google: ' . $e->getMessage());
            header('Location: /mi-empresa/crm/agenda');
            exit;
        }
    }

    public function googleCallback(): void
    {
        AuthService::requireLogin();

        $code = trim((string) ($_GET['code'] ?? ''));
        $state = trim((string) ($_GET['state'] ?? ''));
        $error = trim((string) ($_GET['error'] ?? ''));

        if ($error !== '') {
            Flash::set('danger', 'Google denegó la conexión: ' . $error);
            header('Location: /mi-empresa/crm/agenda');
            exit;
        }

        if ($code === '' || $state === '') {
            Flash::set('danger', 'Callback OAuth inválido (falta code o state).');
            header('Location: /mi-empresa/crm/agenda');
            exit;
        }

        try {
            $result = $this->oauthService->handleCallback($code, $state);
            Flash::set('success', 'Google Calendar conectado con éxito para ' . ($result['google_email'] ?? 'tu cuenta') . '.');
        } catch (\Throwable $e) {
            Flash::set('danger', 'Error al procesar el callback de Google: ' . $e->getMessage());
        }

        header('Location: /mi-empresa/crm/agenda');
        exit;
    }

    public function googleDisconnect(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $usuarioId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

        $mode = $this->oauthService->resolveAgendaMode($empresaId);
        $targetUser = $mode === 'empresa' ? null : $usuarioId;

        $this->oauthService->disconnect($empresaId, $targetUser);
        Flash::set('success', 'Conexión con Google Calendar eliminada.');

        header('Location: /mi-empresa/crm/agenda');
        exit;
    }

    // ---- Helpers internos ----

    private function maybePushToGoogle(array $event): void
    {
        try {
            (new GoogleCalendarSyncService($this->repository, $this->oauthService))->push($event);
        } catch (\Throwable) {
            // Silencioso: el sync a Google no debe bloquear el guardado local.
        }
    }

    private function validateRequest(array $input, int $empresaId, ?array $actual): array
    {
        $errors = [];

        $titulo = trim((string) ($input['titulo'] ?? ''));
        $descripcion = trim((string) ($input['descripcion'] ?? ''));
        $ubicacion = trim((string) ($input['ubicacion'] ?? ''));
        $inicioInput = trim((string) ($input['inicio'] ?? ''));
        $finInput = trim((string) ($input['fin'] ?? ''));
        $allDay = !empty($input['all_day']) ? 1 : 0;
        $color = trim((string) ($input['color'] ?? ''));
        $estado = trim((string) ($input['estado'] ?? 'programado'));

        if ($titulo === '') {
            $errors['titulo'] = 'El título es obligatorio.';
        } elseif (mb_strlen($titulo) > 200) {
            $errors['titulo'] = 'El título no puede superar los 200 caracteres.';
        }

        $inicio = $this->parseDateTimeInput($inicioInput);
        if ($inicio === null) {
            $errors['inicio'] = 'Fecha de inicio inválida.';
        }

        $fin = $this->parseDateTimeInput($finInput);
        if ($fin === null) {
            $errors['fin'] = 'Fecha de fin inválida.';
        }

        if ($inicio !== null && $fin !== null && $fin < $inicio) {
            $errors['fin'] = 'La fecha de fin no puede ser anterior al inicio.';
        }

        if ($color === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = AgendaRepository::defaultColorFor('manual');
        }

        if (!in_array($estado, AgendaRepository::ESTADOS, true)) {
            $estado = 'programado';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'empresa_id' => $empresaId,
            'usuario_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            'usuario_nombre' => $_SESSION['user_name'] ?? 'Usuario',
            'titulo' => $titulo,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'ubicacion' => $ubicacion !== '' ? $ubicacion : null,
            'inicio' => $inicio->format('Y-m-d H:i:s'),
            'fin' => $fin->format('Y-m-d H:i:s'),
            'all_day' => $allDay,
            'color' => $color,
            'estado' => $estado,
            'origen_tipo' => 'manual',
            'origen_id' => null,
        ];
    }

    private function hydrateFormState(array $evento): array
    {
        return [
            'id' => (int) ($evento['id'] ?? 0),
            'titulo' => (string) ($evento['titulo'] ?? ''),
            'descripcion' => (string) ($evento['descripcion'] ?? ''),
            'ubicacion' => (string) ($evento['ubicacion'] ?? ''),
            'inicio' => $this->formatDateTimeForInput($evento['inicio'] ?? null),
            'fin' => $this->formatDateTimeForInput($evento['fin'] ?? null),
            'all_day' => (int) ($evento['all_day'] ?? 0),
            'color' => (string) ($evento['color'] ?? '#6c757d'),
            'estado' => (string) ($evento['estado'] ?? 'programado'),
            'origen_tipo' => (string) ($evento['origen_tipo'] ?? 'manual'),
        ];
    }

    private function rebuildFormStateFromPost(array $input, ?array $actual = null): array
    {
        $state = $actual !== null ? $this->hydrateFormState($actual) : [
            'id' => null,
            'titulo' => '',
            'descripcion' => '',
            'ubicacion' => '',
            'inicio' => '',
            'fin' => '',
            'all_day' => 0,
            'color' => AgendaRepository::defaultColorFor('manual'),
            'estado' => 'programado',
            'origen_tipo' => 'manual',
        ];

        $state['titulo'] = trim((string) ($input['titulo'] ?? $state['titulo']));
        $state['descripcion'] = trim((string) ($input['descripcion'] ?? $state['descripcion']));
        $state['ubicacion'] = trim((string) ($input['ubicacion'] ?? $state['ubicacion']));
        $state['inicio'] = trim((string) ($input['inicio'] ?? $state['inicio']));
        $state['fin'] = trim((string) ($input['fin'] ?? $state['fin']));
        $state['all_day'] = !empty($input['all_day']) ? 1 : 0;
        $state['color'] = trim((string) ($input['color'] ?? $state['color']));
        $state['estado'] = trim((string) ($input['estado'] ?? $state['estado']));

        return $state;
    }

    private function buildUiContext(): array
    {
        return [
            'basePath' => '/mi-empresa/crm/agenda',
            'indexPath' => '/mi-empresa/crm/agenda',
            'dashboardPath' => OperationalAreaService::dashboardPath(OperationalAreaService::AREA_CRM),
            'helpPath' => OperationalAreaService::helpPath(OperationalAreaService::AREA_CRM),
            'moduleNotesKey' => 'crm_agenda',
            'moduleNotesLabel' => 'Agenda CRM',
        ];
    }

    private function normalizeDateParam(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }

    private function parseDateTimeInput(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d'];
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date;
            }
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function formatDateTimeForInput(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }
        try {
            return (new DateTimeImmutable($value))->format('Y-m-d\TH:i');
        } catch (\Exception) {
            return '';
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
