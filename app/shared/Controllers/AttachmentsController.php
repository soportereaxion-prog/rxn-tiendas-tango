<?php

declare(strict_types=1);

namespace App\Shared\Controllers;

use App\Core\Context;
use App\Core\Controller;
use App\Core\CsrfHelper;
use App\Core\Database;
use App\Core\Services\AttachmentService;
use App\Modules\Auth\AuthService;
use PDO;
use Throwable;

/**
 * Endpoints únicos de adjuntos polimórficos.
 *
 * Rutas (definidas en app/config/routes.php):
 *   POST /attachments/upload          → JSON
 *   POST /attachments/{id}/delete     → JSON
 *   GET  /attachments/{id}/download   → binary stream
 *
 * Responsabilidades:
 *  - Auth (requireLogin) + empresa context.
 *  - CSRF en POST.
 *  - Validar que el owner (owner_type + owner_id) pertenezca a la empresa
 *    ANTES de llamar al service. Defensa contra IDOR cruzado (ej: usuario de
 *    empresa A intenta adjuntar a una nota de empresa B).
 *  - Delegar en AttachmentService toda la mecánica (validación MIME, storage,
 *    persistencia). Este controller no toca filesystem.
 */
class AttachmentsController extends Controller
{
    private AttachmentService $service;

    public function __construct()
    {
        $this->service = new AttachmentService();
    }

    public function upload(): void
    {
        AuthService::requireLogin();
        $this->verifyCsrfOrAbort();

        header('Content-Type: application/json; charset=utf-8');

        $empresaId = Context::getEmpresaId();
        if ($empresaId === null || $empresaId <= 0) {
            $this->jsonError('Contexto de empresa inválido.', 400);
        }

        $ownerType = (string) ($_POST['owner_type'] ?? '');
        $ownerId   = (int) ($_POST['owner_id'] ?? 0);

        if ($ownerType === '' || $ownerId <= 0) {
            $this->jsonError('Parámetros owner_type / owner_id inválidos.', 400);
        }

        if (!$this->ownerBelongsToEmpresa($ownerType, $ownerId, $empresaId)) {
            $this->jsonError('El registro dueño no existe o no pertenece a la empresa activa.', 403);
        }

        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) {
            $this->jsonError('No se recibió archivo.', 400);
        }

        try {
            $result = $this->service->attach(
                $empresaId,
                $ownerType,
                $ownerId,
                $file,
                $_SESSION['user_id'] ?? null
            );
            echo json_encode([
                'success'    => true,
                'attachment' => $result,
            ]);
            exit;
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            // Excepciones controladas del service: mensaje user-friendly seguro.
            $this->jsonError($e->getMessage(), 422);
        } catch (Throwable $e) {
            // Cualquier otra (PDO, filesystem, etc.): no exponer detalle.
            error_log('[AttachmentsController::upload] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->jsonError('Error interno al procesar el adjunto.', 500);
        }
    }

    public function delete(string $id): void
    {
        AuthService::requireLogin();
        $this->verifyCsrfOrAbort();

        header('Content-Type: application/json; charset=utf-8');

        $empresaId = Context::getEmpresaId();
        if ($empresaId === null || $empresaId <= 0) {
            $this->jsonError('Contexto de empresa inválido.', 400);
        }

        try {
            $ok = $this->service->delete((int) $id, (int) $empresaId);
            echo json_encode(['success' => $ok]);
            exit;
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->jsonError($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('[AttachmentsController::delete] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->jsonError('Error interno al eliminar el adjunto.', 500);
        }
    }

    public function preview(string $id): void
    {
        AuthService::requireLogin();

        $empresaId = Context::getEmpresaId();
        if ($empresaId === null || $empresaId <= 0) {
            http_response_code(400);
            echo 'Contexto de empresa inválido.';
            exit;
        }

        try {
            $info = $this->service->getForPreview((int) $id, (int) $empresaId);
        } catch (Throwable $e) {
            http_response_code(404);
            echo 'Preview no disponible.';
            exit;
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        // Inline, acotado por CSP y nosniff. El CSP 'none' + img-src 'self'
        // neutraliza cualquier intento de ejecutar scripts embebidos.
        header('Content-Type: ' . $info['mime']);
        header('Content-Disposition: inline; filename="' . $this->sanitizeFilenameHeader($info['original_name']) . '"');
        header('Content-Length: ' . $info['size_bytes']);
        header('Cache-Control: private, max-age=60');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; object-src 'none'; script-src 'none'; style-src 'none';");

        $fp = @fopen($info['abs_path'], 'rb');
        if ($fp === false) {
            http_response_code(500);
            echo 'No se pudo leer el archivo.';
            exit;
        }
        while (!feof($fp)) {
            $chunk = fread($fp, 8192);
            if ($chunk === false) {
                break;
            }
            echo $chunk;
            @ob_flush();
            flush();
        }
        fclose($fp);
        exit;
    }

    public function download(string $id): void
    {
        AuthService::requireLogin();

        $empresaId = Context::getEmpresaId();
        if ($empresaId === null || $empresaId <= 0) {
            http_response_code(400);
            echo 'Contexto de empresa inválido.';
            exit;
        }

        try {
            $info = $this->service->getForDownload((int) $id, (int) $empresaId);
        } catch (Throwable $e) {
            http_response_code(404);
            echo 'Adjunto no disponible.';
            exit;
        }

        // Forzamos descarga (no render inline) para TODO — previene XSS via
        // contenido malicioso y es consistente con la política de seguridad.
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $info['mime']);
        header('Content-Disposition: attachment; filename="' . $this->sanitizeFilenameHeader($info['original_name']) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, private');
        header('Pragma: private');
        header('Content-Length: ' . $info['size_bytes']);
        header('X-Content-Type-Options: nosniff');

        $fp = @fopen($info['abs_path'], 'rb');
        if ($fp === false) {
            http_response_code(500);
            echo 'No se pudo leer el archivo.';
            exit;
        }

        // Stream en chunks — no cargar en memoria.
        while (!feof($fp)) {
            $chunk = fread($fp, 8192);
            if ($chunk === false) {
                break;
            }
            echo $chunk;
            @ob_flush();
            flush();
        }
        fclose($fp);
        exit;
    }

    /* ------------------------------------------------------------------ */

    /**
     * Verifica que (owner_type, owner_id) sea una fila real que pertenezca a la empresa.
     * Whitelist rígida por owner_type — no se aceptan tipos desconocidos.
     */
    private function ownerBelongsToEmpresa(string $ownerType, int $ownerId, int $empresaId): bool
    {
        $map = [
            'crm_nota'        => 'crm_notas',
            'crm_presupuesto' => 'crm_presupuestos',
        ];

        if (!isset($map[$ownerType])) {
            return false;
        }

        $table = $map[$ownerType];
        $sql = "SELECT 1 FROM {$table} WHERE id = :id AND empresa_id = :empresa_id LIMIT 1";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([':id' => $ownerId, ':empresa_id' => $empresaId]);
        return $stmt->fetchColumn() !== false;
    }

    private function jsonError(string $message, int $httpCode = 400): void
    {
        http_response_code($httpCode);
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }

    /**
     * Limpia el filename para el header Content-Disposition.
     * Previene CRLF injection y caracteres que rompen el header.
     */
    private function sanitizeFilenameHeader(string $name): string
    {
        $name = str_replace(["\r", "\n", '"'], ' ', $name);
        return trim($name);
    }
}
