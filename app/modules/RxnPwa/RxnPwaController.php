<?php

declare(strict_types=1);

namespace App\Modules\RxnPwa;

use App\Core\Context;
use App\Core\CsrfHelper;
use App\Core\RateLimiter;
use App\Core\Services\AttachmentService;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\CrmPresupuestos\PresupuestoTangoService;
use App\Modules\Empresas\EmpresaAccessService;
use Throwable;

/**
 * Entry de la PWA mobile (Bloque A — Fase 1).
 *
 * Rutas:
 *   - GET /rxnpwa/presupuestos             → shell HTML que registra el SW + chequea catálogo offline.
 *   - GET /api/rxnpwa/catalog/version      → {hash, generated_at, items_count, size_bytes}.
 *   - GET /api/rxnpwa/catalog/full         → catálogo completo + hash.
 *
 * Auth: cookie de sesión + acceso CRM. Multi-tenant estricto via Context::getEmpresaId().
 */
class RxnPwaController extends \App\Core\Controller
{
    private RxnPwaCatalogService $catalog;

    public function __construct()
    {
        $this->catalog = new RxnPwaCatalogService();
    }

    /**
     * Launcher / sub-menú raíz de la PWA. Muestra todas las PWAs disponibles
     * (Presupuestos, Horas, etc.) como cards. Pre-cacheado por el SW. Es la
     * landing por default cuando el operador toca "Abrir PWA" desde el banner
     * del backoffice.
     */
    public function launcher(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        $empresaId = (int) Context::getEmpresaId();

        View::render('app/modules/RxnPwa/views/launcher.php', [
            'empresaId' => $empresaId,
            'pageTitle' => 'RXN PWA — Apps Mobile',
        ]);
    }

    /**
     * Shell HTML de la PWA. Esta vista es lo que termina cacheada por el SW
     * como app shell. Contiene un mínimo: header + zona "preparando offline" +
     * indicador de versión de catálogo. La fase 2 reemplaza/extiende esto con el
     * formulario mobile real.
     */
    public function presupuestosShell(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        $empresaId = (int) Context::getEmpresaId();

        View::render('app/modules/RxnPwa/views/presupuestos_shell.php', [
            'empresaId' => $empresaId,
            'pageTitle' => 'RXN PWA — Presupuestos',
        ]);
    }

    /**
     * Form mobile para crear un presupuesto nuevo. El draft local se crea
     * client-side al primer save (tmp_uuid se genera en JS).
     */
    public function presupuestoNuevo(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        $empresaId = (int) Context::getEmpresaId();

        View::render('app/modules/RxnPwa/views/presupuesto_form.php', [
            'empresaId' => $empresaId,
            'tmpUuid' => '',
            'pageTitle' => 'Nuevo presupuesto',
        ]);
    }

    /**
     * Form mobile para editar un draft local existente. La carga real del draft
     * la hace el JS desde IndexedDB usando el tmp_uuid de la URL.
     */
    public function presupuestoEditar(string $tmpUuid): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        $empresaId = (int) Context::getEmpresaId();
        $tmpUuid = $this->sanitizeTmpUuid($tmpUuid);

        View::render('app/modules/RxnPwa/views/presupuesto_form.php', [
            'empresaId' => $empresaId,
            'tmpUuid' => $tmpUuid,
            'pageTitle' => 'Editar presupuesto',
        ]);
    }

    private function sanitizeTmpUuid(string $tmpUuid): string
    {
        $tmpUuid = trim($tmpUuid);
        if (!preg_match('/^TMP-[A-Za-z0-9-]{1,64}$/', $tmpUuid)) {
            return '';
        }
        return $tmpUuid;
    }

    public function catalogVersion(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        $empresaId = (int) Context::getEmpresaId();
        $version = $this->catalog->ensureVersion($empresaId);

        $this->jsonResponse([
            'ok' => true,
            'hash' => $version['hash'],
            'generated_at' => $version['generated_at'],
            'items_count' => $version['items_count'],
            'size_bytes' => $version['size_bytes'],
        ]);
    }

    public function catalogFull(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        $empresaId = (int) Context::getEmpresaId();
        $bundle = $this->catalog->getFullCatalog($empresaId);

        // Hash en header así el SW puede compararlo sin parsear el JSON entero.
        header('X-Rxnpwa-Catalog-Hash: ' . $bundle['hash']);

        $this->jsonResponse([
            'ok' => true,
            'hash' => $bundle['hash'],
            'generated_at' => $bundle['generated_at'],
            'items_count' => $bundle['items_count'],
            'size_bytes' => $bundle['size_bytes'],
            'data' => $bundle['data'],
        ]);
    }

    /* =================================================================
     * PWA HORAS (turnero CrmHoras) — release 1.43.0.
     * Sin Tango (no aplica). Adjuntos sí (certificados, fotos del trabajo).
     * ================================================================= */

    public function horasShell(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        $empresaId = (int) Context::getEmpresaId();
        View::render('app/modules/RxnPwa/views/horas_shell.php', [
            'empresaId' => $empresaId,
            'pageTitle' => 'RXN PWA — Horas',
        ]);
    }

    public function horasNuevo(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        $empresaId = (int) Context::getEmpresaId();
        View::render('app/modules/RxnPwa/views/horas_form.php', [
            'empresaId' => $empresaId,
            'tmpUuid' => '',
            'pageTitle' => 'Nuevo turno',
        ]);
    }

    public function horasEditar(string $tmpUuid): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();
        $empresaId = (int) Context::getEmpresaId();
        $tmpUuid = $this->sanitizeTmpUuid($tmpUuid);
        View::render('app/modules/RxnPwa/views/horas_form.php', [
            'empresaId' => $empresaId,
            'tmpUuid' => $tmpUuid,
            'pageTitle' => 'Editar turno',
        ]);
    }

    /**
     * POST /api/rxnpwa/horas/sync
     * Persiste un turno cargado desde la PWA mobile. Idempotente por tmp_uuid_pwa.
     */
    public function syncHora(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['ok' => false, 'error' => 'Método no permitido.'], 405);
            return;
        }
        if (!$this->checkCsrf()) return;
        if (!$this->checkRateLimit('pwa_horas_sync', 60, 60)) return;

        $empresaId = (int) Context::getEmpresaId();
        $usuarioId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $usuarioNombre = (string) ($_SESSION['user_name'] ?? 'PWA Mobile');

        $raw = file_get_contents('php://input');
        $draft = json_decode((string) $raw, true);
        if (!is_array($draft)) {
            $this->jsonResponse(['ok' => false, 'error' => 'JSON inválido.'], 400);
            return;
        }

        try {
            $service = new RxnPwaHorasSyncService();
            $result = $service->syncDraft($draft, $empresaId, $usuarioId, $usuarioNombre);
            $this->jsonResponse($result, 200);
        } catch (Throwable $e) {
            error_log('[RxnPwa::syncHora] ' . $e->getMessage());
            $this->jsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/rxnpwa/horas/{id}/attachments
     * Sube un adjunto al turno ya sincronizado. owner_type='crm_hora'.
     */
    public function uploadHoraAttachment(string $id): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['ok' => false, 'error' => 'Método no permitido.'], 405);
            return;
        }
        if (!$this->checkCsrf()) return;
        if (!$this->checkRateLimit('pwa_horas_upload', 120, 60)) return;

        $empresaId = (int) Context::getEmpresaId();
        $horaId = (int) $id;
        if ($horaId <= 0) {
            $this->jsonResponse(['ok' => false, 'error' => 'ID de turno inválido.'], 400);
            return;
        }

        // IDOR check: el turno debe pertenecer a la empresa.
        $repo = new \App\Modules\CrmHoras\HoraRepository();
        $hora = $repo->findById($horaId, $empresaId);
        if ($hora === null) {
            $this->jsonResponse(['ok' => false, 'error' => 'El turno no existe en esta empresa.'], 404);
            return;
        }

        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) {
            $this->jsonResponse(['ok' => false, 'error' => 'No se recibió ningún archivo (campo "file").'], 400);
            return;
        }

        $usuarioId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        try {
            $service = new AttachmentService();
            $attached = $service->attach($empresaId, 'crm_hora', $horaId, $file, $usuarioId);
            $this->jsonResponse(['ok' => true, 'attachment' => $attached], 200);
        } catch (Throwable $e) {
            error_log('[RxnPwa::uploadHoraAttachment] ' . $e->getMessage());
            $this->jsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /* =================================================================
     * Fase 3 (Bloque C) — Sync queue + envío a Tango.
     * ================================================================= */

    /**
     * POST /api/rxnpwa/presupuestos/sync
     *
     * Recibe el draft offline (cabecera + renglones + tmp_uuid) y crea el
     * presupuesto server-side. Idempotente por `tmp_uuid_pwa`.
     *
     * Body: JSON con shape de IndexedDB → ver RxnPwaSyncService::buildPayload.
     * Response 200: { ok, id_server, numero, tmp_uuid, created }.
     * Response 4xx: { ok:false, error:string }.
     */
    public function syncPresupuesto(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['ok' => false, 'error' => 'Método no permitido.'], 405);
            return;
        }
        if (!$this->checkCsrf()) return;
        if (!$this->checkRateLimit('pwa_sync', 60, 60)) return;

        $empresaId = (int) Context::getEmpresaId();
        $usuarioId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $usuarioNombre = (string) ($_SESSION['user_name'] ?? 'PWA Mobile');

        $raw = file_get_contents('php://input');
        $draft = json_decode((string) $raw, true);
        if (!is_array($draft)) {
            $this->jsonResponse(['ok' => false, 'error' => 'JSON inválido.'], 400);
            return;
        }

        try {
            $service = new RxnPwaSyncService();
            $result = $service->syncDraft($draft, $empresaId, $usuarioId, $usuarioNombre);

            // Geo tracking del presupuesto creado offline en campo (release 1.37.0).
            // Solo registramos si fue una creación nueva (no idempotent re-sync).
            if (!empty($result['created']) && $result['id_server'] > 0) {
                $this->recordGeoEvent($result['id_server'], $draft['geo'] ?? null);
            }

            $this->jsonResponse($result, 200);
        } catch (Throwable $e) {
            error_log('[RxnPwa::syncPresupuesto] ' . $e->getMessage());
            $this->jsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Registra el evento `presupuesto.created` en RxnGeoTracking. Si el cliente
     * mandó lat/lng del GPS del celu, los reporta encima del fallback IP que
     * hace `registrar()` por default. Fail-silent — la geo no debe romper el sync.
     */
    private function recordGeoEvent(int $presupuestoId, $geoFromClient): void
    {
        try {
            $geoService = new \App\Modules\RxnGeoTracking\GeoTrackingService();
            $eventoId = $geoService->registrar(
                \App\Modules\RxnGeoTracking\GeoTrackingService::EVENT_PRESUPUESTO_CREATED,
                $presupuestoId,
                'presupuesto'
            );
            if ($eventoId === null) return;

            // Reportar posición precisa del celu (si llegó).
            if (is_array($geoFromClient)) {
                $lat = isset($geoFromClient['lat']) && is_numeric($geoFromClient['lat']) ? (float) $geoFromClient['lat'] : null;
                $lng = isset($geoFromClient['lng']) && is_numeric($geoFromClient['lng']) ? (float) $geoFromClient['lng'] : null;
                $accuracy = isset($geoFromClient['accuracy']) && is_numeric($geoFromClient['accuracy']) ? (int) $geoFromClient['accuracy'] : null;
                $source = (string) ($geoFromClient['source'] ?? 'error');
                // El service valida rangos y normaliza source. Solo reportamos si
                // hay coordenadas — sino el evento queda con el fallback IP del registrar().
                if ($lat !== null && $lng !== null) {
                    $geoService->reportarPosicionBrowser($eventoId, $lat, $lng, $accuracy, $source);
                } elseif ($source === 'denied') {
                    // El user denegó: igual marcamos el source para auditoría.
                    $geoService->reportarPosicionBrowser($eventoId, null, null, null, 'denied');
                }
            }
        } catch (\Throwable $e) {
            error_log('[RxnPwa::recordGeoEvent] ' . $e->getMessage());
        }
    }

    /**
     * POST /api/rxnpwa/presupuestos/{id}/attachments
     *
     * Sube un archivo asociado al presupuesto server-side. multipart/form-data
     * con campo 'file'. Reusa AttachmentService (owner_type = 'crm_presupuesto').
     *
     * Response 200: { ok, attachment: { id, original_name, size_bytes, mime } }.
     * Response 4xx: { ok:false, error:string }.
     */
    public function uploadAttachment(string $id): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['ok' => false, 'error' => 'Método no permitido.'], 405);
            return;
        }
        if (!$this->checkCsrf()) return;
        if (!$this->checkRateLimit('pwa_upload', 120, 60)) return;

        $empresaId = (int) Context::getEmpresaId();
        $presupuestoId = (int) $id;
        if ($presupuestoId <= 0) {
            $this->jsonResponse(['ok' => false, 'error' => 'ID de presupuesto inválido.'], 400);
            return;
        }

        // IDOR check: el presupuesto debe pertenecer a la empresa.
        $repo = new \App\Modules\CrmPresupuestos\PresupuestoRepository();
        $presupuesto = $repo->findById($presupuestoId, $empresaId);
        if ($presupuesto === null) {
            $this->jsonResponse(['ok' => false, 'error' => 'El presupuesto no existe en esta empresa.'], 404);
            return;
        }

        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) {
            $this->jsonResponse(['ok' => false, 'error' => 'No se recibió ningún archivo (campo "file").'], 400);
            return;
        }

        $usuarioId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        try {
            $service = new AttachmentService();
            $attached = $service->attach($empresaId, 'crm_presupuesto', $presupuestoId, $file, $usuarioId);
            $this->jsonResponse(['ok' => true, 'attachment' => $attached], 200);
        } catch (Throwable $e) {
            error_log('[RxnPwa::uploadAttachment] ' . $e->getMessage());
            $this->jsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/rxnpwa/presupuestos/{id}/emit-tango
     *
     * Dispara el envío a Tango del presupuesto server-side. Reusa
     * `PresupuestoTangoService::send()` — la misma lógica que el form web.
     *
     * Solo se debe llamar si hay red. El cliente PWA controla esto disabling
     * el botón cuando navigator.onLine === false.
     *
     * Response 200: { ok, type, message, ...result }.
     */
    public function emitTango(string $id): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['ok' => false, 'error' => 'Método no permitido.'], 405);
            return;
        }
        if (!$this->checkCsrf()) return;
        if (!$this->checkRateLimit('pwa_emit', 20, 60)) return;

        $empresaId = (int) Context::getEmpresaId();
        $presupuestoId = (int) $id;
        if ($presupuestoId <= 0) {
            $this->jsonResponse(['ok' => false, 'error' => 'ID de presupuesto inválido.'], 400);
            return;
        }

        try {
            $service = new PresupuestoTangoService();
            $result = $service->send($presupuestoId, $empresaId);
            $status = ($result['ok'] ?? false) ? 200 : 422;
            $this->jsonResponse($result, $status);
        } catch (Throwable $e) {
            error_log('[RxnPwa::emitTango] ' . $e->getMessage());
            $this->jsonResponse(['ok' => false, 'type' => 'danger', 'message' => $e->getMessage()], 500);
        }
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        // No cachear en proxies/SW: la frescura la maneja el cliente con el hash.
        header('Cache-Control: no-store, max-age=0');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Valida `X-CSRF-Token` contra el de la sesión. Si falla, escribe 403 y
     * devuelve false — el caller hace `return;` inmediato.
     */
    private function checkCsrf(): bool
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!CsrfHelper::validate(is_string($token) ? $token : null)) {
            $this->jsonResponse(['ok' => false, 'error' => 'CSRF token inválido o ausente.'], 403);
            return false;
        }
        return true;
    }

    /**
     * Throttle por (scope, user_id, empresa_id). Si excede, 429 + retry-after.
     */
    private function checkRateLimit(string $scope, int $maxAttempts, int $windowSeconds): bool
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $empresaId = (int) Context::getEmpresaId();
        $key = $scope . ':u' . $userId . ':e' . $empresaId;
        if (!RateLimiter::attempt($key, $maxAttempts, $windowSeconds)) {
            $retry = RateLimiter::retryAfter($key);
            header('Retry-After: ' . $retry);
            $this->jsonResponse([
                'ok' => false,
                'error' => 'Demasiados intentos. Esperá ' . $retry . ' segundos y reintentá.',
                'retry_after' => $retry,
            ], 429);
            return false;
        }
        return true;
    }
}
