<?php

declare(strict_types=1);

namespace App\Core\Services;

use App\Core\Database;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use PDO;
use Throwable;

/**
 * WebPushService — emisión de notificaciones push a navegadores suscritos.
 *
 * Lo invoca NotificationService::notify() automáticamente al final del flujo
 * (fire-and-forget). Si el usuario no tiene suscripciones activas o falla VAPID,
 * la notificación in-app sigue funcionando — el push es complemento, no
 * sustituto.
 *
 * Endpoints HTTP relevantes:
 *  - POST /mi-perfil/web-push/subscribe   — guarda una sub nueva.
 *  - POST /mi-perfil/web-push/unsubscribe — borra una sub por endpoint.
 *  - GET  /api/internal/web-push/vapid-key — devuelve solo la public key
 *    (la consume el frontend para applicationServerKey).
 *
 * Cleanup automático: si un push devuelve 410 Gone (browser desinstalado /
 * sub revocada), se borra la sub. Si devuelve 5xx, se incrementa
 * failed_attempts; con 5+ fallos la sub queda `disabled_at` y se ignora en
 * próximos envíos.
 */
class WebPushService
{
    private PDO $db;
    private ?WebPush $webPush = null;
    private ?string $vapidPublicKey = null;
    private bool $vapidConfigured = false;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->bootVapid();
    }

    private function bootVapid(): void
    {
        $publicKey = (string) ($_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?: '');
        $privateKey = (string) ($_ENV['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY') ?: '');
        $subject = (string) ($_ENV['VAPID_SUBJECT'] ?? getenv('VAPID_SUBJECT') ?: '');

        if ($publicKey === '' || $privateKey === '' || $subject === '') {
            return;
        }

        try {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject'    => $subject,
                    'publicKey'  => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);
            $this->vapidPublicKey = $publicKey;
            $this->vapidConfigured = true;
        } catch (Throwable) {
            $this->vapidConfigured = false;
        }
    }

    public function isConfigured(): bool
    {
        return $this->vapidConfigured;
    }

    public function getPublicKey(): ?string
    {
        return $this->vapidPublicKey;
    }

    /**
     * Guarda una nueva suscripción o actualiza p256dh/auth si el endpoint ya existía.
     */
    public function subscribe(int $empresaId, int $usuarioId, string $endpoint, string $p256dh, string $auth, ?string $userAgent = null): bool
    {
        if ($empresaId <= 0 || $usuarioId <= 0 || $endpoint === '' || $p256dh === '' || $auth === '') {
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO web_push_subscriptions
                (empresa_id, usuario_id, endpoint, p256dh, auth, user_agent)
             VALUES
                (:empresa_id, :usuario_id, :endpoint, :p256dh, :auth, :user_agent)
             ON DUPLICATE KEY UPDATE
                empresa_id = VALUES(empresa_id),
                usuario_id = VALUES(usuario_id),
                p256dh = VALUES(p256dh),
                auth = VALUES(auth),
                user_agent = VALUES(user_agent),
                failed_attempts = 0,
                disabled_at = NULL'
        );

        return $stmt->execute([
            ':empresa_id' => $empresaId,
            ':usuario_id' => $usuarioId,
            ':endpoint'   => $endpoint,
            ':p256dh'     => $p256dh,
            ':auth'       => $auth,
            ':user_agent' => $userAgent,
        ]);
    }

    public function unsubscribe(int $empresaId, int $usuarioId, string $endpoint): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM web_push_subscriptions
              WHERE empresa_id = :e AND usuario_id = :u AND endpoint = :endpoint'
        );
        return $stmt->execute([':e' => $empresaId, ':u' => $usuarioId, ':endpoint' => $endpoint]);
    }

    public function countActive(int $empresaId, int $usuarioId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM web_push_subscriptions
              WHERE empresa_id = :e AND usuario_id = :u AND disabled_at IS NULL'
        );
        $stmt->execute([':e' => $empresaId, ':u' => $usuarioId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Envía un push a todas las subs activas del usuario.
     *
     * @param array<string,mixed> $data Payload extra (se serializa con title/body/link/icon)
     * @return array{sent:int, failed:int, removed:int}
     */
    public function sendToUser(int $empresaId, int $usuarioId, string $title, ?string $body, ?string $link, array $data = []): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'removed' => 0];
        if (!$this->vapidConfigured || $this->webPush === null) {
            return $result;
        }

        $stmt = $this->db->prepare(
            'SELECT id, endpoint, p256dh, auth FROM web_push_subscriptions
              WHERE empresa_id = :e AND usuario_id = :u AND disabled_at IS NULL'
        );
        $stmt->execute([':e' => $empresaId, ':u' => $usuarioId]);
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($subs === []) {
            return $result;
        }

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'link'  => $link,
            'data'  => $data,
            'icon'  => '/img/rxn-icon-192.png',
        ], JSON_UNESCAPED_UNICODE);

        $endpointById = [];
        foreach ($subs as $row) {
            $sub = Subscription::create([
                'endpoint'        => $row['endpoint'],
                'publicKey'       => $row['p256dh'],
                'authToken'       => $row['auth'],
                'contentEncoding' => 'aes128gcm',
            ]);
            $this->webPush->queueNotification($sub, $payload);
            $endpointById[$row['endpoint']] = (int) $row['id'];
        }

        try {
            foreach ($this->webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                $subId = $endpointById[$endpoint] ?? null;
                if ($report->isSuccess()) {
                    $result['sent']++;
                    if ($subId !== null) {
                        $this->markSent($subId);
                    }
                    continue;
                }
                $statusCode = $report->getResponse() ? $report->getResponse()->getStatusCode() : 0;
                if ($statusCode === 404 || $statusCode === 410) {
                    if ($subId !== null) {
                        $this->removeById($subId);
                    }
                    $result['removed']++;
                } else {
                    if ($subId !== null) {
                        $this->markFailed($subId);
                    }
                    $result['failed']++;
                }
            }
        } catch (Throwable) {
            $result['failed'] += count($subs);
        }

        return $result;
    }

    private function markSent(int $subId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE web_push_subscriptions
                SET last_push_at = NOW(), failed_attempts = 0
              WHERE id = :id'
        );
        $stmt->execute([':id' => $subId]);
    }

    private function markFailed(int $subId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE web_push_subscriptions
                SET failed_attempts = failed_attempts + 1,
                    disabled_at = CASE WHEN failed_attempts + 1 >= 5 THEN NOW() ELSE NULL END
              WHERE id = :id'
        );
        $stmt->execute([':id' => $subId]);
    }

    private function removeById(int $subId): void
    {
        $stmt = $this->db->prepare('DELETE FROM web_push_subscriptions WHERE id = :id');
        $stmt->execute([':id' => $subId]);
    }
}
