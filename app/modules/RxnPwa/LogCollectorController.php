<?php

declare(strict_types=1);

namespace App\Modules\RxnPwa;

use App\Core\Context;
use App\Modules\Auth\AuthService;

/**
 * Captura logs del cliente PWA (errores JS, warnings, console) y los persiste
 * en `storage/logs/rxnpwa-client.log` para descarga del admin.
 *
 * Pensado para debug remoto: el operador no puede abrir DevTools del celu y
 * mandar screenshots, así que el cliente JS hookea window.onerror /
 * unhandledrejection / etc. y los manda acá. El admin descarga el txt y se lo
 * pasa a soporte.
 *
 * Sin auth en el endpoint POST (cualquier cliente PWA puede reportar). Auth
 * obligatoria en el GET de descarga (solo admin).
 */
class LogCollectorController
{
    private const MAX_LINE_LEN = 4000;
    private const MAX_FILE_BYTES = 5 * 1024 * 1024; // 5 MB. Más allá rota.

    public function record(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed';
            return;
        }

        $raw = file_get_contents('php://input');
        $payload = json_decode((string) $raw, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo 'JSON inválido';
            return;
        }

        $level   = $this->sanitize((string) ($payload['level'] ?? 'info'), 16);
        $message = $this->sanitize((string) ($payload['message'] ?? ''), 1500);
        $stack   = $this->sanitize((string) ($payload['stack'] ?? ''), 1500);
        $url     = $this->sanitize((string) ($payload['url'] ?? ''), 300);
        $ua      = $this->sanitize((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 300);
        $empresaId = (int) (Context::getEmpresaId() ?? 0);
        $userId    = (int) ($_SESSION['user_id'] ?? 0);

        $line = sprintf(
            "[%s] [%s] empresa=%d user=%d url=%s ua=%s :: %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $empresaId,
            $userId,
            $url,
            $ua,
            $message,
            $stack !== '' ? ' || stack=' . $stack : ''
        );
        $line = mb_substr($line, 0, self::MAX_LINE_LEN, 'UTF-8');

        $logPath = $this->ensureLogDir() . '/rxnpwa-client.log';

        // Rotación simple: si el archivo supera 5MB, lo movemos a .old y arrancamos uno nuevo.
        if (is_file($logPath) && filesize($logPath) >= self::MAX_FILE_BYTES) {
            @rename($logPath, $logPath . '.old');
        }

        @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);

        http_response_code(204);
    }

    /**
     * GET /admin/rxnpwa-logs/download
     * Descarga el txt completo. Solo admin del tenant.
     */
    public function download(): void
    {
        AuthService::requireLogin();
        if (!AuthService::hasAdminPrivileges()) {
            http_response_code(403);
            echo '403 — Solo admin';
            return;
        }

        $logPath = $this->ensureLogDir() . '/rxnpwa-client.log';
        if (!is_file($logPath)) {
            http_response_code(404);
            echo 'Sin logs todavía. Hacé que el cliente PWA dispare al menos un evento desde el celu.';
            return;
        }

        $filename = 'rxnpwa-client-' . date('Ymd-His') . '.log';
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($logPath));
        readfile($logPath);
    }

    private function ensureLogDir(): string
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function sanitize(string $value, int $maxLen): string
    {
        // Quitar caracteres de control y newlines (rompen el formato del log).
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $clean = trim($clean);
        return mb_substr($clean, 0, $maxLen, 'UTF-8');
    }
}
