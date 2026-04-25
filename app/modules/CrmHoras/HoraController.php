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

        try {
            $newId = $this->service->iniciar($empresaId, $userId, $concepto, $lat, $lng, $consent, $tratativaId, $pdsId, $clienteId);
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
     */
    public function diferido(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        [$empresaId] = $this->ctx();

        View::render('app/modules/CrmHoras/views/diferido.php', [
            'tratativas' => $this->loadTratativasActivas($empresaId),
        ]);
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

        try {
            $this->service->cargarDiferido($empresaId, $userId, $startedAt, $endedAt, $concepto, $lat, $lng, $consent, $tratativaId, $pdsId, $clienteId);
            $_SESSION['flash_success'] = 'Turno cargado.';
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

        View::render('app/modules/CrmHoras/views/index.php', [
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'usuarioFilter' => $usuarioFilter,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);
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
