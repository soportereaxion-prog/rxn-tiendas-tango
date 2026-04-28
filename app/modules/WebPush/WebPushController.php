<?php

declare(strict_types=1);

namespace App\Modules\WebPush;

use App\Core\Context;
use App\Core\Controller;
use App\Core\Services\WebPushService;
use App\Modules\Auth\AuthService;

/**
 * Endpoints de gestión de suscripciones Web Push del usuario autenticado.
 *
 * Las rutas públicas (sin sesión) viven en App\Modules\WebPush\PublicController
 * para mantener separados los flujos.
 */
class WebPushController extends Controller
{
    private WebPushService $service;

    public function __construct()
    {
        $this->service = new WebPushService();
    }

    private function ctx(): array
    {
        $empresaId = (int) Context::getEmpresaId();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($empresaId <= 0 || $userId <= 0) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'sin_contexto']);
            exit;
        }
        return [$empresaId, $userId];
    }

    /**
     * POST /mi-perfil/web-push/subscribe
     * Body x-www-form-urlencoded: csrf_token, endpoint, p256dh, auth.
     */
    public function subscribe(): void
    {
        AuthService::requireLogin();
        $this->verifyCsrfOrAbort();
        [$empresaId, $userId] = $this->ctx();

        header('Content-Type: application/json; charset=utf-8');

        $endpoint = (string) ($_POST['endpoint'] ?? '');
        $p256dh   = (string) ($_POST['p256dh'] ?? '');
        $auth     = (string) ($_POST['auth'] ?? '');
        $ua       = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'payload_invalido']);
            return;
        }

        $ok = $this->service->subscribe($empresaId, $userId, $endpoint, $p256dh, $auth, $ua);
        echo json_encode([
            'ok'     => $ok,
            'active' => $this->service->countActive($empresaId, $userId),
        ]);
    }

    /**
     * POST /mi-perfil/web-push/unsubscribe
     * Body x-www-form-urlencoded: csrf_token, endpoint.
     */
    public function unsubscribe(): void
    {
        AuthService::requireLogin();
        $this->verifyCsrfOrAbort();
        [$empresaId, $userId] = $this->ctx();

        header('Content-Type: application/json; charset=utf-8');

        $endpoint = (string) ($_POST['endpoint'] ?? '');

        if ($endpoint === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'endpoint_requerido']);
            return;
        }

        $this->service->unsubscribe($empresaId, $userId, $endpoint);
        echo json_encode([
            'ok'     => true,
            'active' => $this->service->countActive($empresaId, $userId),
        ]);
    }

    /**
     * GET /mi-perfil/web-push/status
     * Devuelve cantidad de subs activas + public key para el frontend.
     */
    public function status(): void
    {
        AuthService::requireLogin();
        [$empresaId, $userId] = $this->ctx();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'              => true,
            'configured'      => $this->service->isConfigured(),
            'active'          => $this->service->countActive($empresaId, $userId),
            'vapid_public_key' => $this->service->getPublicKey(),
        ]);
    }

    /**
     * POST /mi-perfil/web-push/test
     *
     * Diagnóstico: dispara un push de prueba directo al usuario actual sin pasar
     * por NotificationService. Devuelve el resumen real de WebPushService::sendToUser
     * (sent / failed / removed) para que la UI muestre exactamente qué pasó.
     *
     * Útil para aislar si el problema es server-side (VAPID, lib, sub vencida)
     * o browser/SO (Focus Assist, background killed, etc).
     */
    public function test(): void
    {
        AuthService::requireLogin();
        $this->verifyCsrfOrAbort();
        [$empresaId, $userId] = $this->ctx();

        header('Content-Type: application/json; charset=utf-8');

        if (!$this->service->isConfigured()) {
            echo json_encode([
                'ok' => false,
                'error' => 'vapid_no_configurado',
                'hint' => 'Faltan VAPID_PUBLIC_KEY/VAPID_PRIVATE_KEY/VAPID_SUBJECT en el .env del server.',
            ]);
            return;
        }

        $active = $this->service->countActive($empresaId, $userId);
        if ($active === 0) {
            echo json_encode([
                'ok' => false,
                'error' => 'sin_suscripciones',
                'hint' => 'Tu usuario no tiene suscripciones activas en este server. Volvé a Activar el card.',
            ]);
            return;
        }

        $started = microtime(true);
        $result = $this->service->sendToUser(
            $empresaId,
            $userId,
            '🧪 Push de prueba — RxnSuite',
            'Si ves este toast del SO, el flujo Web Push está OK end-to-end. Si solo lo ves en la campanita, el problema está en el navegador o el SO (Focus Assist, background).',
            '/mi-perfil',
            ['source' => 'diagnostic_test']
        );
        $elapsedMs = (int) round((microtime(true) - $started) * 1000);

        echo json_encode([
            'ok'         => true,
            'subs_total' => $active,
            'sent'       => $result['sent'],
            'failed'     => $result['failed'],
            'removed'    => $result['removed'],
            'elapsed_ms' => $elapsedMs,
        ]);
    }
}
