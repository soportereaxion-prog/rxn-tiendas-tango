<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * TrackingController (Fase 5) — endpoints PÚBLICOS sin login usados en
 * el HTML del mail para registrar aperturas y clicks.
 *
 * URLs:
 *   GET /m/open/{token}.gif   → registra open + devuelve pixel 1x1
 *   GET /m/click/{token}      → registra click con ?u=<url> + redirige
 *
 * Security:
 *   - El token (bin2hex(random_bytes(24)) = 48 chars) funciona como shared
 *     secret: quien lo tiene, llegó a través del mail real. No hay auth
 *     adicional porque los mails se distribuyen ampliamente y los clientes
 *     de correo no llevan cookies.
 *   - El pixel responde SIEMPRE 200 + gif válido, incluso si el token no
 *     matchea, para no romper la UX del cliente de mail.
 *   - El click redirige a la URL `u` sólo si pasa validación estricta
 *     (filter_var FILTER_VALIDATE_URL + whitelist de schemes http/https)
 *     para evitar open-redirects.
 */
class TrackingController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Endpoint del pixel. El token puede venir con ".gif" sufijo (es parte
     * del path, ya lo saca el router).
     */
    public function open(string $token): void
    {
        // Limpiar sufijo .gif si el router lo pasó incluido
        $token = preg_replace('/\.gif$/i', '', trim($token)) ?? $token;

        try {
            $this->registerEvent($token, 'open', null);
        } catch (Throwable $e) {
            // Nunca romper la respuesta del pixel
            error_log('[TrackingController::open] ' . $e->getMessage());
        }

        // Respuesta: GIF 1x1 transparente
        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate, private');
        header('Pragma: no-cache');
        header('Expires: 0');
        // 43 bytes de gif transparente 1x1
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }

    /**
     * Endpoint del redirect. Recibe el token y un parámetro GET `u` con
     * la URL de destino. Registra el click y redirige.
     */
    public function click(string $token): void
    {
        $token = trim($token);
        $url = (string) ($_GET['u'] ?? '');

        // Validar URL: debe ser http/https, sin caracteres raros, con host.
        $validated = $this->validateRedirectUrl($url);

        try {
            $this->registerEvent($token, 'click', $validated);
        } catch (Throwable $e) {
            error_log('[TrackingController::click] ' . $e->getMessage());
        }

        if ($validated === null) {
            http_response_code(400);
            echo 'Invalid URL';
            exit;
        }

        header('Location: ' . $validated, true, 302);
        exit;
    }

    // ──────────────────────────────────────────────

    private function registerEvent(string $token, string $tipo, ?string $url): void
    {
        if ($token === '' || strlen($token) < 20) return; // descarta basura

        // Buscar item por token (devuelve empresa_id + item_id)
        $stmt = $this->db->prepare(
            "SELECT id, empresa_id FROM crm_mail_job_items
             WHERE tracking_token = :t LIMIT 1"
        );
        $stmt->execute([':t' => $token]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) return; // token desconocido, silencioso

        $ins = $this->db->prepare(
            "INSERT INTO crm_mail_tracking_events
                (job_item_id, empresa_id, tipo, url_clicked, ip, user_agent)
             VALUES (:iid, :emp, :tipo, :url, :ip, :ua)"
        );
        $ins->execute([
            ':iid' => (int) $item['id'],
            ':emp' => (int) $item['empresa_id'],
            ':tipo' => $tipo,
            ':url' => $url !== null ? mb_substr($url, 0, 1024) : null,
            ':ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]);
    }

    /**
     * Valida la URL de redirect. Acepta solo http/https, con host no vacío,
     * sin javascript:/data:/file:/etc. Devuelve la URL segura o null si no pasa.
     */
    private function validateRedirectUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') return null;

        if (!filter_var($url, FILTER_VALIDATE_URL)) return null;

        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) return null;

        $scheme = strtolower((string) $parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) return null;

        return $url;
    }
}
