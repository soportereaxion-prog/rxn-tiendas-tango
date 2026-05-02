<?php

declare(strict_types=1);

namespace App\Modules\CrmHoras;

use App\Core\Context;
use App\Core\Controller;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\Empresas\EmpresaAccessService;

class HoraController extends Controller
{
    private HoraRepository $repository;
    private HoraService $service;

    public function __construct()
    {
        $this->repository = new HoraRepository();
        $this->service = new HoraService($this->repository);
    }

    private function ctx(): array
    {
        $empresaId = (int) (Context::getEmpresaId() ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($empresaId <= 0 || $userId <= 0) {
            header('Location: /');
            exit;
        }
        return [$empresaId, $userId];
    }

    /**
     * GET /mi-empresa/crm/horas
     * Vista turnero principal — botón único contextual + lista del día.
     * Mobile-first.
     */
    /**
     * Lista de tratativas activas para alimentar el selector del form.
     * Devuelve hasta 30 ordenadas por created_at DESC. Para volúmenes mayores
     * conviene migrar a Spotlight (queda como TODO en MODULE_CONTEXT).
     *
     * @return array<int,array{id:int,numero:int,titulo:string}>
     */
    private function loadTratativasActivas(int $empresaId): array
    {
        try {
            $pdo = \App\Core\Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT id, numero, titulo
                FROM crm_tratativas
                WHERE empresa_id = :e
                  AND deleted_at IS NULL
                  AND estado IN ('nueva', 'en_curso', 'pausada')
                ORDER BY created_at DESC
                LIMIT 30
            ");
            $stmt->execute([':e' => $empresaId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function turnero(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        [$empresaId, $userId] = $this->ctx();

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $abierto = $this->repository->findOpenByUser($empresaId, $userId);
        $delDia  = $this->repository->findTodayByUser($empresaId, $userId, $today);
        $tratativas = $this->loadTratativasActivas($empresaId);

        // Total trabajado hoy (suma de duraciones de turnos cerrados + duración del abierto en vivo).
        $totalSeg = 0;
        foreach ($delDia as $h) {
            if ($h['estado'] === 'anulado') continue;
            try {
                $start = new \DateTimeImmutable((string) $h['started_at']);
                $end = $h['ended_at'] ? new \DateTimeImmutable((string) $h['ended_at']) : new \DateTimeImmutable();
                $totalSeg += max(0, $end->getTimestamp() - $start->getTimestamp());
            } catch (\Throwable) {}
        }

        View::render('app/modules/CrmHoras/views/turnero.php', [
            'abierto'    => $abierto,
            'turnos'     => $delDia,
            'totalSeg'   => $totalSeg,
            'today'      => $today,
            'tratativas' => $tratativas,
        ]);
    }

    /**
     * POST /mi-empresa/crm/horas/iniciar
     * Inicia un turno en vivo. Recibe geo opcional + concepto opcional.
     */
    public function iniciar(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        $this->verifyCsrfOrAbort();
        [$empresaId, $userId] = $this->ctx();

        $concepto = trim((string) ($_POST['concepto'] ?? ''));
        $lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float) $_POST['lat'] : null;
        $lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float) $_POST['lng'] : null;
        $consent = !empty($_POST['geo_consent']);
        $tratativaId = (int) ($_POST['tratativa_id'] ?? 0);
        $pdsId = (int) ($_POST['pds_id'] ?? 0);
        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $descuentoSegundos = $this->parseDurationOrZero((string) ($_POST['descuento'] ?? ''));
        $motivoDescuento = trim((string) ($_POST['motivo_descuento'] ?? ''));

        try {
            $newId = $this->service->iniciar($empresaId, $userId, $concepto, $lat, $lng, $consent, $tratativaId, $pdsId, $clienteId, $descuentoSegundos, $motivoDescuento !== '' ? $motivoDescuento : null);
            $_SESSION['flash_success'] = 'Turno iniciado (#' . $newId . ').';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        header('Location: /mi-empresa/crm/horas');
        exit;
    }

    /**
     * POST /mi-empresa/crm/horas/cerrar
     * Cierra el turno abierto del usuario. Geo opcional al cierre.
     */
    public function cerrar(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        $this->verifyCsrfOrAbort();
        [$empresaId, $userId] = $this->ctx();

        $lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float) $_POST['lat'] : null;
        $lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float) $_POST['lng'] : null;
        $consent = !empty($_POST['geo_consent']);

        try {
            $this->service->cerrar($empresaId, $userId, $lat, $lng, $consent);
            $_SESSION['flash_success'] = 'Turno cerrado.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        header('Location: /mi-empresa/crm/horas');
        exit;
    }

    /**
     * GET /mi-empresa/crm/horas/diferido
     * Form para cargar un turno post-facto.
     *
     * Si el caller es admin del tenant, el form muestra un selector "Cargar
     * para…" con los usuarios activos de la empresa, permitiendo cargar el
     * turno en nombre de otro. Para no-admins, el form sigue siendo personal.
     */
    public function diferido(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        [$empresaId] = $this->ctx();

        $usuariosTenant = [];
        if (AuthService::hasAdminPrivileges()) {
            $usuariosTenant = $this->loadUsuariosActivos($empresaId);
        }

        View::render('app/modules/CrmHoras/views/diferido.php', [
            'tratativas'     => $this->loadTratativasActivas($empresaId),
            'usuariosTenant' => $usuariosTenant,
            'esAdmin'        => AuthService::hasAdminPrivileges(),
        ]);
    }

    /**
     * Lista de usuarios activos del tenant — alimenta el selector "cargar para"
     * del form diferido cuando el caller es admin. Devuelve [id => nombre].
     *
     * @return array<int,string>
     */
    private function loadUsuariosActivos(int $empresaId): array
    {
        try {
            $pdo = \App\Core\Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT id, nombre
                FROM usuarios
                WHERE empresa_id = :e AND activo = 1 AND deleted_at IS NULL
                ORDER BY nombre ASC
            ");
            $stmt->execute([':e' => $empresaId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $result = [];
            foreach ($rows as $r) {
                $result[(int) $r['id']] = (string) $r['nombre'];
            }
            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * POST /mi-empresa/crm/horas/diferido
     * Guarda el turno cargado a posteriori.
     */
    public function diferidoStore(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        $this->verifyCsrfOrAbort();
        [$empresaId, $userId] = $this->ctx();

        $startedAt = (string) ($_POST['started_at'] ?? '');
        $endedAt   = (string) ($_POST['ended_at'] ?? '');
        $concepto  = trim((string) ($_POST['concepto'] ?? ''));
        $lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float) $_POST['lat'] : null;
        $lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float) $_POST['lng'] : null;
        $consent = !empty($_POST['geo_consent']);
        $tratativaId = (int) ($_POST['tratativa_id'] ?? 0);
        $pdsId = (int) ($_POST['pds_id'] ?? 0);
        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $descuentoSegundos = $this->parseDurationOrZero((string) ($_POST['descuento'] ?? ''));
        $motivoDescuento = trim((string) ($_POST['motivo_descuento'] ?? ''));

        // Quién carga (actor) vs sobre quién se carga (owner). Por default
        // coinciden — solo admins del tenant pueden disociarlos. Validamos
        // que el target sea un usuario activo de la misma empresa para
        // bloquear cross-tenant IDOR.
        $ownerUserId = $userId;
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        if ($targetUserId > 0 && $targetUserId !== $userId && AuthService::hasAdminPrivileges()) {
            $usuarios = $this->loadUsuariosActivos($empresaId);
            if (isset($usuarios[$targetUserId])) {
                $ownerUserId = $targetUserId;
            }
        }

        try {
            $this->service->cargarDiferido(
                $empresaId,
                $ownerUserId,
                $startedAt,
                $endedAt,
                $concepto,
                $lat,
                $lng,
                $consent,
                $tratativaId,
                $pdsId,
                $clienteId,
                $userId,  // actor — quien dispara la operación
                $descuentoSegundos,
                $motivoDescuento !== '' ? $motivoDescuento : null
            );
            $_SESSION['flash_success'] = $ownerUserId === $userId
                ? 'Turno cargado.'
                : 'Turno cargado en nombre de otro usuario.';
            header('Location: /mi-empresa/crm/horas');
            exit;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /mi-empresa/crm/horas/diferido');
            exit;
        }
    }

    /**
     * GET /mi-empresa/crm/horas/listado
     * Vista admin/supervisor con todos los turnos.
     */
    public function listado(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        [$empresaId] = $this->ctx();

        $usuarioFilter = (int) ($_GET['usuario_id'] ?? 0) ?: null;
        $desde = trim((string) ($_GET['desde'] ?? ''));
        $hasta = trim((string) ($_GET['hasta'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;

        $result = $this->repository->paginate($empresaId, $usuarioFilter, $desde ?: null, $hasta ?: null, $page, $perPage);
        $totalPages = max(1, (int) ceil($result['total'] / $perPage));

        // Enriquecer con el último audit de cada turno — para mostrar badge
        // "Editado por X el DD/MM HH:MM" en el listado. Una sola query batch.
        $items = $result['items'];
        $auditByHora = $this->loadLastAudits($empresaId, array_map(static fn($r) => (int) $r['id'], $items));
        foreach ($items as &$row) {
            $row['last_audit'] = $auditByHora[(int) $row['id']] ?? null;
        }
        unset($row);

        View::render('app/modules/CrmHoras/views/index.php', [
            'items' => $items,
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'usuarioFilter' => $usuarioFilter,
            'desde' => $desde,
            'hasta' => $hasta,
            'esAdmin' => AuthService::hasAdminPrivileges(),
        ]);
    }

    /**
     * Devuelve, para una lista de hora_ids, el último audit de cada uno
     * (accion + performed_by_nombre + performed_at + motivo). Una sola query
     * batch. Vacío si la lista está vacía.
     *
     * @param int[] $horaIds
     * @return array<int,array<string,mixed>>
     */
    private function loadLastAudits(int $empresaId, array $horaIds): array
    {
        if (empty($horaIds)) return [];
        $placeholders = implode(',', array_fill(0, count($horaIds), '?'));
        $pdo = \App\Core\Database::getConnection();
        $sql = "
            SELECT a.hora_id, a.accion, a.motivo, a.performed_at, a.performed_by, u.nombre AS performed_by_nombre
            FROM crm_horas_audit a
            INNER JOIN (
                SELECT hora_id, MAX(id) AS max_id
                FROM crm_horas_audit
                WHERE empresa_id = ? AND hora_id IN ($placeholders)
                GROUP BY hora_id
            ) latest ON latest.max_id = a.id
            LEFT JOIN usuarios u ON u.id = a.performed_by
            WHERE a.empresa_id = ?
        ";
        $params = array_merge([$empresaId], $horaIds, [$empresaId]);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
            $result[(int) $r['hora_id']] = $r;
        }
        return $result;
    }

    /**
     * GET /mi-empresa/crm/horas/{id}/editar
     * Form de edición de un turno — exclusivo admin del tenant.
     */
    public function editarForm(int $id): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        if (!AuthService::hasAdminPrivileges()) {
            http_response_code(403);
            echo "<h2>403 — Solo admin</h2>";
            exit;
        }
        [$empresaId] = $this->ctx();

        $hora = $this->repository->findById($id, $empresaId);
        if ($hora === null) {
            http_response_code(404);
            echo "<h2>404 — Turno no encontrado</h2>";
            exit;
        }

        $usuarios = $this->loadUsuariosActivos($empresaId);

        View::render('app/modules/CrmHoras/views/editar.php', [
            'hora'     => $hora,
            'usuarios' => $usuarios,
        ]);
    }

    /**
     * POST /mi-empresa/crm/horas/{id}/editar
     * Persiste la edición del turno. Audit obligatorio.
     */
    public function editarStore(int $id): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        $this->verifyCsrfOrAbort();
        if (!AuthService::hasAdminPrivileges()) {
            http_response_code(403);
            echo "<h2>403 — Solo admin</h2>";
            exit;
        }
        [$empresaId, $userId] = $this->ctx();

        $startedAt = (string) ($_POST['started_at'] ?? '');
        $endedAt   = trim((string) ($_POST['ended_at'] ?? ''));
        $concepto  = trim((string) ($_POST['concepto'] ?? ''));
        $motivo    = trim((string) ($_POST['motivo'] ?? ''));
        // descuento+motivo descuento: si vienen en el POST, los aplicamos.
        $descuentoSegundos = isset($_POST['descuento'])
            ? $this->parseDurationOrZero((string) $_POST['descuento'])
            : null;
        $motivoDescuento = isset($_POST['motivo_descuento'])
            ? trim((string) $_POST['motivo_descuento'])
            : null;

        try {
            $this->service->editar(
                $empresaId,
                $id,
                $startedAt,
                $endedAt !== '' ? $endedAt : null,
                $concepto !== '' ? $concepto : null,
                $motivo,
                $userId,
                $descuentoSegundos,
                $motivoDescuento
            );
            $_SESSION['flash_success'] = 'Turno editado.';
            header('Location: /mi-empresa/crm/horas/listado');
            exit;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /mi-empresa/crm/horas/' . $id . '/editar');
            exit;
        }
    }

    /**
     * GET /mi-empresa/crm/horas/{id}
     * Vista detalle del turno — accesible al dueño + admin del tenant. Muestra
     * resumen + adjuntos + form de upload. Es donde un operador común puede
     * cargar certificados médicos / planillas / fotos de su propio turno.
     */
    public function detalle(int $id): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        [$empresaId, $userId] = $this->ctx();

        $hora = $this->repository->findById($id, $empresaId);
        if ($hora === null) {
            $_SESSION['flash_error'] = 'El turno no existe o no pertenece a tu empresa.';
            header('Location: /mi-empresa/crm/horas');
            exit;
        }

        $esAdmin = AuthService::hasAdminPrivileges();
        $esDueno = (int) $hora['usuario_id'] === $userId;
        if (!$esAdmin && !$esDueno) {
            http_response_code(403);
            echo "<h2>403 — No podés ver un turno ajeno.</h2>";
            exit;
        }

        // Mostrar quién es el dueño si es distinto al caller (caso admin).
        $ownerNombre = $hora['usuario_nombre'] ?? null;
        if ($ownerNombre === null) {
            $usuarios = $this->loadUsuariosActivos($empresaId);
            $ownerNombre = $usuarios[(int) $hora['usuario_id']] ?? ('Usuario #' . (int) $hora['usuario_id']);
        }

        $adjuntos = [];
        try {
            $service = new \App\Core\Services\AttachmentService();
            $adjuntos = $service->listByOwner($empresaId, 'crm_hora', $id);
        } catch (\Throwable) {}

        View::render('app/modules/CrmHoras/views/detalle.php', [
            'hora'         => $hora,
            'ownerNombre'  => $ownerNombre,
            'adjuntos'     => $adjuntos,
            'esAdmin'      => $esAdmin,
            'esDueno'      => $esDueno,
        ]);
    }

    /**
     * POST /mi-empresa/crm/horas/{id}/adjuntos — subida web (multipart con campo `file`).
     * Reusa AttachmentService con owner_type='crm_hora'.
     */
    public function uploadAdjunto(int $id): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        $this->verifyCsrfOrAbort();
        [$empresaId, $userId] = $this->ctx();

        $hora = $this->repository->findById($id, $empresaId);
        if ($hora === null) {
            $_SESSION['flash_error'] = 'El turno no existe o no pertenece a la empresa.';
            header('Location: /mi-empresa/crm/horas');
            exit;
        }

        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) {
            $_SESSION['flash_error'] = 'No se recibió ningún archivo.';
            header('Location: /mi-empresa/crm/horas/' . $id . '/editar');
            exit;
        }

        try {
            $service = new \App\Core\Services\AttachmentService();
            $service->attach($empresaId, 'crm_hora', $id, $file, $userId);
            $_SESSION['flash_success'] = 'Adjunto cargado.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Error al adjuntar: ' . $e->getMessage();
        }
        header('Location: /mi-empresa/crm/horas/' . $id);
        exit;
    }

    /**
     * POST /mi-empresa/crm/horas/{id}/adjuntos/{attId}/borrar — soft-delete del adjunto.
     */
    public function deleteAdjunto(int $id, int $attId): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        $this->verifyCsrfOrAbort();
        [$empresaId, $userId] = $this->ctx();

        $hora = $this->repository->findById($id, $empresaId);
        if ($hora === null) {
            $_SESSION['flash_error'] = 'El turno no existe.';
            header('Location: /mi-empresa/crm/horas');
            exit;
        }

        try {
            $service = new \App\Core\Services\AttachmentService();
            $service->delete($attId, $empresaId);
            $_SESSION['flash_success'] = 'Adjunto eliminado.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Error al borrar: ' . $e->getMessage();
        }
        header('Location: /mi-empresa/crm/horas/' . $id . '/editar');
        exit;
    }

    /**
     * Parsea HH:MM:SS o MM:SS o entero de segundos en duración.
     * Retorna 0 si vacío o inválido (no rompe el form).
     */
    private function parseDurationOrZero(string $value): int
    {
        $value = trim($value);
        if ($value === '') return 0;
        if (preg_match('/^(\d{1,3}):([0-5]?\d):([0-5]?\d)$/', $value, $m)) {
            return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + ((int) $m[3]);
        }
        if (preg_match('/^(\d{1,3}):([0-5]?\d)$/', $value, $m)) {
            return ((int) $m[1]) * 60 + ((int) $m[2]);
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        return 0;
    }

    /**
     * POST /mi-empresa/crm/horas/{id}/anular
     */
    public function anular(int $id): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        $this->verifyCsrfOrAbort();
        [$empresaId, $userId] = $this->ctx();

        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        try {
            $this->service->anular($empresaId, $id, $motivo, $userId);
            $_SESSION['flash_success'] = 'Turno anulado.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        header('Location: /mi-empresa/crm/horas');
        exit;
    }
}

class HoraAuditController extends Controller
{
    /**
     * GET /admin/horas/audit
     * Listado del audit log de mutaciones admin sobre crm_horas.
     * Visible SOLO para super admin (rxn admin).
     */
    public function index(): void
    {
        AuthService::requireRxnAdmin();
        $empresaId = (int) (Context::getEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            header('Location: /admin/dashboard');
            exit;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 50;
        $repo = new HoraAuditRepository();
        $items = $repo->paginate($empresaId, $page, $perPage);
        $total = $repo->countAll($empresaId);
        $totalPages = max(1, (int) ceil($total / $perPage));

        View::render('app/modules/CrmHoras/views/audit.php', [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
        ]);
    }
}
