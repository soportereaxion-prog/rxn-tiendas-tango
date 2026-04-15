<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos;

use App\Core\Context;
use App\Core\Flash;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\CrmMailMasivos\Services\BatchProcessor;
use App\Modules\CrmMailMasivos\Services\JobDispatcher;
use InvalidArgumentException;
use Throwable;

/**
 * JobController — Envíos Masivos (Fase 4).
 *
 * Pantallas:
 *   index()    → listado de jobs de la empresa
 *   create()   → pantalla de disparo (elegir reporte + plantilla + preview)
 *   monitor()  → pantalla de monitoreo de un job en curso/terminado
 *
 * Endpoints AJAX (login required):
 *   previewRecipients() → POST JSON {report_id} → {count, mails[]}
 *   status()            → GET JSON → estado + contadores para polling liviano
 *   cancel()            → POST → setea cancel_flag
 *
 * Endpoint público con token:
 *   callback()          → POST JSON de n8n con updates de estado/items
 */
class JobController
{
    private JobRepository $repo;

    public function __construct()
    {
        $this->repo = new JobRepository();
    }

    // ──────────────────────────────────────────────
    // LISTADO + MONITOR
    // ──────────────────────────────────────────────

    public function index(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        $search = trim((string) ($_GET['search'] ?? ''));
        $jobs = $this->repo->findAllByEmpresa($empresaId, $search);

        View::render('app/modules/CrmMailMasivos/views/envios/index.php', [
            'jobs' => $jobs,
            'search' => $search,
        ]);
    }

    public function create(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $usuarioId = (int) ($_SESSION['user_id'] ?? 0);

        $choices = $this->repo->listChoices($empresaId);

        // Cargar SMTP del usuario para mostrar en el form (confirmación visual)
        $ctx = $this->repo->loadJobContext($empresaId, $usuarioId, 0, 0);

        View::render('app/modules/CrmMailMasivos/views/envios/crear.php', [
            'reports' => $choices['reports'],
            'templates' => $choices['templates'],
            'smtp' => $ctx['smtp'],
        ]);
    }

    public function monitor(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $jobId = (int) $id;

        $job = $this->repo->findByIdForEmpresa($jobId, $empresaId);
        if (!$job) {
            http_response_code(404);
            echo 'Envío no encontrado.';
            return;
        }

        View::render('app/modules/CrmMailMasivos/views/envios/monitor.php', [
            'job' => $job,
            'items' => $this->repo->findItemsForJob($jobId, $empresaId, 200),
            'tracking' => $this->repo->findTrackingSummaryForJob($jobId, $empresaId),
        ]);
    }

    // ──────────────────────────────────────────────
    // ENDPOINTS AJAX (login required)
    // ──────────────────────────────────────────────

    /**
     * POST JSON {report_id} → {success, count, mails[0..9], total_warning?}
     * Ejecuta el query del reporte, dedup, filtra inválidos, devuelve conteo
     * y muestra los primeros 10 para que el usuario confirme antes de disparar.
     */
    public function previewRecipients(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $empresaId = (int) Context::getEmpresaId();
        $raw = file_get_contents('php://input');
        $in = $raw ? json_decode($raw, true) : null;

        $reportId = (int) ($in['report_id'] ?? 0);
        if ($reportId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Falta report_id']);
            exit;
        }

        try {
            $dispatcher = new JobDispatcher($this->repo);
            $preview = $dispatcher->previewRecipients($reportId, $empresaId);

            // Solo los primeros 10 para la UI
            $sample = array_slice($preview['mails'], 0, 10);
            $sampleClean = array_map(fn($m) => [
                'email' => $m['email'],
                'name' => $m['name'],
            ], $sample);

            echo json_encode([
                'success' => true,
                'count' => $preview['count'],
                'sample' => $sampleClean,
                'capped' => $preview['count'] >= JobDispatcher::MAX_RECIPIENTS,
            ]);
            exit;
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage(), 'kind' => 'validation']);
            exit;
        } catch (Throwable $e) {
            http_response_code(500);
            error_log('JobController::previewRecipients error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST form {report_id, template_id, confirm=yes} → dispara el job.
     * No es JSON, es un submit de form real para mantener el flujo tradicional.
     */
    public function store(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $usuarioId = (int) ($_SESSION['user_id'] ?? 0);

        $reportId = (int) ($_POST['report_id'] ?? 0);
        $templateId = (int) ($_POST['template_id'] ?? 0);
        $confirm = (string) ($_POST['confirm'] ?? '');

        if ($confirm !== 'yes') {
            Flash::set('danger', 'Tenés que marcar la confirmación antes de disparar el envío.');
            header('Location: /mi-empresa/crm/mail-masivos/envios/crear');
            exit;
        }

        try {
            $dispatcher = new JobDispatcher($this->repo);
            $result = $dispatcher->dispatch($empresaId, $usuarioId, $reportId, $templateId);

            $msg = 'Envío disparado. Job #' . $result['job_id']
                . ' · ' . $result['total_destinatarios'] . ' destinatarios.';
            if (!empty($result['warnings'])) {
                Flash::set('warning', $msg . ' ' . implode(' ', $result['warnings']));
            } else {
                Flash::set('success', $msg);
            }

            header('Location: /mi-empresa/crm/mail-masivos/envios/' . $result['job_id']);
            exit;
        } catch (InvalidArgumentException $e) {
            Flash::set('danger', $e->getMessage());
            header('Location: /mi-empresa/crm/mail-masivos/envios/crear');
            exit;
        } catch (Throwable $e) {
            error_log('JobController::store error: ' . $e->getMessage());
            Flash::set('danger', 'Error interno al disparar el envío: ' . $e->getMessage());
            header('Location: /mi-empresa/crm/mail-masivos/envios/crear');
            exit;
        }
    }

    /**
     * GET JSON — polling liviano del monitor.
     */
    public function status(string $id): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $empresaId = (int) Context::getEmpresaId();
        $jobId = (int) $id;

        $live = $this->repo->findLiveStatusForEmpresa($jobId, $empresaId);
        if (!$live) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Envío no encontrado']);
            exit;
        }

        echo json_encode(['success' => true, 'job' => $live]);
        exit;
    }

    /**
     * POST — setea cancel_flag. El workflow n8n lo ve entre iteraciones.
     */
    public function cancel(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $jobId = (int) $id;

        $ok = $this->repo->setCancelFlag($jobId, $empresaId);
        Flash::set($ok ? 'success' : 'danger',
            $ok ? 'Cancelación solicitada. n8n va a cortar en el próximo batch.'
                : 'No se pudo solicitar la cancelación (¿ya estaba finalizado?).');

        header('Location: /mi-empresa/crm/mail-masivos/envios/' . $jobId);
        exit;
    }

    // ──────────────────────────────────────────────
    // ENDPOINT DE PROCESAMIENTO (público, protegido por token)
    // Llamado por n8n en loop con pause_seconds entre llamadas.
    // También lo puede disparar tools/process_mail_job.php directamente.
    // ──────────────────────────────────────────────

    /**
     * POST JSON { job_id, batch_size? } con header X-RXN-Token.
     *
     * Procesa un batch de items pending del job y devuelve el estado
     * actualizado para que el caller decida si seguir llamando o no.
     *
     * El response incluye `is_final: bool` — si es true, n8n corta el loop.
     * El response incluye `pause_seconds` — n8n espera ese tiempo antes
     * de volver a llamar.
     */
    public function processBatch(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $expectedToken = (string) ($_ENV['N8N_CALLBACK_TOKEN'] ?? getenv('N8N_CALLBACK_TOKEN') ?: '');
        $receivedToken = (string) ($_SERVER['HTTP_X_RXN_TOKEN'] ?? '');

        if ($expectedToken === '' || !hash_equals($expectedToken, $receivedToken)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token inválido']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $in = $raw ? json_decode($raw, true) : null;
        $jobId = (int) ($in['job_id'] ?? 0);
        $batchSize = (int) ($in['batch_size'] ?? 50);

        if ($jobId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'job_id requerido']);
            exit;
        }

        try {
            $processor = new BatchProcessor();
            $result = $processor->processBatch($jobId, $batchSize);
            echo json_encode($result);
            exit;
        } catch (Throwable $e) {
            error_log('JobController::processBatch error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
            exit;
        }
    }

    // ──────────────────────────────────────────────
    // CALLBACK PÚBLICO (protegido por token en header)
    // ──────────────────────────────────────────────

    /**
     * Endpoint llamado por n8n para empujar updates de estado de job o items.
     * Protegido con token en header X-RXN-Token.
     *
     * Payload soportado:
     *   { "type": "job", "job_id": 42, "empresa_id": 1, "patch": { "estado": "running", "started_at": "..." } }
     *   { "type": "item", "tracking_token": "abc...", "empresa_id": 1, "patch": { "estado": "sent", "sent_at": "..." } }
     *   { "type": "item_batch", "items": [ {tracking_token, empresa_id, patch}, ... ] }
     */
    public function callback(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $expectedToken = (string) ($_ENV['N8N_CALLBACK_TOKEN'] ?? getenv('N8N_CALLBACK_TOKEN') ?: '');
        $receivedToken = (string) ($_SERVER['HTTP_X_RXN_TOKEN'] ?? '');

        if ($expectedToken === '' || !hash_equals($expectedToken, $receivedToken)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token inválido']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $in = $raw ? json_decode($raw, true) : null;
        if (!is_array($in)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'JSON inválido']);
            exit;
        }

        $type = (string) ($in['type'] ?? '');

        try {
            if ($type === 'job') {
                $jobId = (int) ($in['job_id'] ?? 0);
                $empresaId = (int) ($in['empresa_id'] ?? 0);
                $patch = is_array($in['patch'] ?? null) ? $in['patch'] : [];
                if ($jobId <= 0 || $empresaId <= 0) {
                    throw new InvalidArgumentException('Falta job_id o empresa_id');
                }
                $ok = $this->repo->updateJobStateFromCallback($jobId, $empresaId, $patch);
                echo json_encode(['success' => $ok]);
                exit;
            }

            if ($type === 'item') {
                $token = (string) ($in['tracking_token'] ?? '');
                $empresaId = (int) ($in['empresa_id'] ?? 0);
                $patch = is_array($in['patch'] ?? null) ? $in['patch'] : [];
                if ($token === '' || $empresaId <= 0) {
                    throw new InvalidArgumentException('Falta tracking_token o empresa_id');
                }
                $ok = $this->repo->updateJobItemState($token, $empresaId, $patch);
                echo json_encode(['success' => $ok]);
                exit;
            }

            if ($type === 'item_batch') {
                $items = is_array($in['items'] ?? null) ? $in['items'] : [];
                $count = 0;
                foreach ($items as $item) {
                    $token = (string) ($item['tracking_token'] ?? '');
                    $empresaId = (int) ($item['empresa_id'] ?? 0);
                    $patch = is_array($item['patch'] ?? null) ? $item['patch'] : [];
                    if ($token === '' || $empresaId <= 0) continue;
                    if ($this->repo->updateJobItemState($token, $empresaId, $patch)) $count++;
                }
                echo json_encode(['success' => true, 'updated' => $count]);
                exit;
            }

            throw new InvalidArgumentException('type desconocido: ' . $type);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        } catch (Throwable $e) {
            error_log('JobController::callback error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno']);
            exit;
        }
    }
}
