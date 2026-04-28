<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Core\Context;
use App\Core\Controller;
use App\Core\Services\NotificationService;
use App\Core\View;
use App\Modules\Auth\AuthService;

class NotificationController extends Controller
{
    private NotificationService $service;

    public function __construct()
    {
        $this->service = new NotificationService();
    }

    private function ctx(): array
    {
        $empresaId = Context::getEmpresaId();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if (!$empresaId || $userId <= 0) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'sin_contexto']);
            exit;
        }
        return [(int) $empresaId, $userId];
    }

    /**
     * GET /notifications/feed.json
     * Devuelve las últimas N notificaciones + contador de no-leídas.
     * Lo consume el dropdown de la campanita en el topbar.
     */
    public function feed(): void
    {
        AuthService::requireLogin();
        [$empresaId, $userId] = $this->ctx();

        // Late firing: antes de devolver el feed, disparamos los recordatorios pendientes
        // del usuario. Esto evita depender de un cron — basta con que el usuario abra la app.
        $this->fireDueReminders($empresaId, $userId);

        $limit = (int) ($_GET['limit'] ?? 5);
        $items = $this->service->latest($empresaId, $userId, $limit);
        $unread = $this->service->countUnread($empresaId, $userId);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'     => true,
            'unread' => $unread,
            'items'  => $items,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Dispara las notificaciones pendientes de recordatorio para el usuario.
     *
     * Patrón: cada módulo que tenga eventos con "fecha de recordatorio" se chequea acá.
     * Por ahora solo CrmNotas, pero la firma del método permite sumar otros (turnos
     * pendientes, presupuestos por vencer, etc.) sin tocar el resto del controller.
     *
     * Idempotente: marca cada fila con su `*_disparado_at` para no re-disparar.
     */
    private function fireDueReminders(int $empresaId, int $userId): void
    {
        try {
            $db = \App\Core\Database::getConnection();
            $stmt = $db->prepare("
                SELECT id, titulo, contenido, cliente_id, tratativa_id, fecha_recordatorio
                FROM crm_notas
                WHERE empresa_id = :empresa_id
                  AND created_by = :user_id
                  AND deleted_at IS NULL
                  AND fecha_recordatorio IS NOT NULL
                  AND recordatorio_disparado_at IS NULL
                  AND fecha_recordatorio <= NOW()
                ORDER BY fecha_recordatorio ASC
                LIMIT 50
            ");
            $stmt->execute([':empresa_id' => $empresaId, ':user_id' => $userId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $markStmt = $db->prepare("UPDATE crm_notas SET recordatorio_disparado_at = NOW() WHERE id = :id AND empresa_id = :empresa_id");

            foreach ($rows as $row) {
                $notaId = (int) $row['id'];
                $titulo = (string) ($row['titulo'] ?? 'Nota');
                $body = trim((string) ($row['contenido'] ?? ''));
                if (mb_strlen($body) > 200) {
                    $body = mb_substr($body, 0, 197) . '...';
                }

                $this->service->notify(
                    empresaId: $empresaId,
                    usuarioId: $userId,
                    type: 'crm_notas.recordatorio',
                    title: '🔔 Recordatorio: ' . $titulo,
                    body: $body !== '' ? $body : null,
                    link: '/mi-empresa/crm/notas/' . $notaId . '/editar',
                    data: ['nota_id' => $notaId],
                    dedupeKey: 'crm_notas.recordatorio.' . $notaId
                );

                $markStmt->execute([':id' => $notaId, ':empresa_id' => $empresaId]);
            }
        } catch (\Throwable) {
            // El late firer no debe romper el feed bajo ningún escenario.
        }
    }

    /**
     * GET /notifications
     * Página "Ver todas" — listado paginado con filtro por leído/no-leído.
     */
    public function index(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($empresaId <= 0 || $userId <= 0) {
            header('Location: /');
            exit;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filter = in_array($_GET['filter'] ?? '', ['unread', 'read'], true) ? $_GET['filter'] : 'all';
        $perPage = 25;

        $result = $this->service->paginate($empresaId, $userId, $page, $perPage, $filter);

        $totalPages = max(1, (int) ceil($result['total'] / $perPage));

        View::render('app/modules/Notifications/views/index.php', [
            'items'      => $result['items'],
            'total'      => $result['total'],
            'unread'     => $result['unread'],
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
            'filter'     => $filter,
        ]);
    }

    /**
     * POST /notifications/{id}/leer
     * Marca una notificación como leída (idempotente).
     * Si la notif tiene link, se devuelve para que el cliente pueda navegar tras
     * la confirmación. La marca de leído ocurre antes del redirect/click.
     */
    public function markRead(int $id): void
    {
        AuthService::requireLogin();
        $this->verifyCsrfOrAbort();
        [$empresaId, $userId] = $this->ctx();

        $this->service->markRead($empresaId, $userId, $id);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
    }

    /**
     * POST /notifications/marcar-todas-leidas
     * Marca como leídas todas las notificaciones no-leídas del usuario.
     */
    public function markAllRead(): void
    {
        AuthService::requireLogin();
        $this->verifyCsrfOrAbort();
        [$empresaId, $userId] = $this->ctx();

        $count = $this->service->markAllRead($empresaId, $userId);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'updated' => $count]);
    }

    /**
     * POST /notifications/{id}/eliminar
     * Soft-delete. Por decisión del rey las notificaciones NO se borran solas;
     * este endpoint existe para casos donde el usuario quiere quitar ruido visible.
     */
    public function softDelete(int $id): void
    {
        AuthService::requireLogin();
        $this->verifyCsrfOrAbort();
        [$empresaId, $userId] = $this->ctx();

        $this->service->softDelete($empresaId, $userId, $id);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
    }
}
