<?php

declare(strict_types=1);

namespace App\Core\Services;

use App\Core\Database;
use App\Core\UploadValidator;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * AttachmentService — único punto de entrada para adjuntos polimórficos.
 *
 * - Tabla: `attachments` (empresa_id, owner_type, owner_id, ...).
 * - Storage: public/uploads/empresas/{empresa_id}/attachments/Y/m/{stored_name}.
 * - Validación: delegada en UploadValidator::anyFile() con whitelist de MIME
 *   + blacklist dura de extensiones ejecutables.
 * - Multi-tenant: TODO método que toque DB o filesystem exige empresa_id.
 * - IDOR: getForDownload() valida que el attachment pertenezca a la empresa solicitada.
 *
 * Owner types soportados en `allowed_owner_types` del config. Si un módulo nuevo
 * quiere adjuntos, se agrega ahí; no hace falta tocar este service.
 */
class AttachmentService
{
    private PDO $db;

    /** @var array<string,mixed> */
    private array $config;

    public function __construct(?PDO $db = null, ?array $config = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->config = $config ?? require BASE_PATH . '/app/config/attachments.php';
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        // Red de seguridad idempotente — la migración es la fuente canónica,
        // esto cubre el caso "devs levantando rama vieja" o instalación manual.
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS attachments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT NOT NULL,
                owner_type VARCHAR(64) NOT NULL,
                owner_id INT NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                mime VARCHAR(128) NOT NULL,
                size_bytes BIGINT UNSIGNED NOT NULL,
                path VARCHAR(500) NOT NULL,
                uploaded_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL DEFAULT NULL,
                KEY idx_attachments_owner (empresa_id, owner_type, owner_id, deleted_at),
                KEY idx_attachments_deleted (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    /* ---------------------------------------------------------------------
     * API pública
     * ------------------------------------------------------------------ */

    /**
     * Adjunta un archivo subido a un owner.
     *
     * @param array $file  Entrada de $_FILES['campo'] (single upload).
     * @return array{id:int, original_name:string, size_bytes:int, mime:string}
     * @throws InvalidArgumentException|RuntimeException
     */
    public function attach(int $empresaId, string $ownerType, int $ownerId, array $file, ?int $uploadedBy = null): array
    {
        $this->assertEmpresa($empresaId);
        $this->assertOwnerType($ownerType);
        if ($ownerId <= 0) {
            throw new InvalidArgumentException('owner_id inválido.');
        }

        // Validación dura (MIME + blacklist + tamaño por archivo).
        $validated = UploadValidator::anyFile(
            $file,
            $this->config['allowed_mime_to_ext'],
            $this->config['blocked_extensions'],
            (int) $this->config['max_file_size_bytes']
        );
        if ($validated === null) {
            throw new InvalidArgumentException('No se recibió ningún archivo.');
        }

        // Topes acumulados por owner.
        $this->assertQuota($empresaId, $ownerType, $ownerId, (int) $validated['size']);

        // Directorio destino: public/uploads/empresas/{empresa_id}/attachments/Y/m/
        [$absDir, $relDir] = $this->buildOwnerDir($empresaId);
        UploadValidator::prepareDir($absDir);
        $this->ensureDenyHtaccess($absDir);

        $storedName = UploadValidator::generateFilename(
            'att',
            $empresaId,
            $validated['ext'],
            $ownerType . '-' . $ownerId
        );
        $absPath = $absDir . DIRECTORY_SEPARATOR . $storedName;
        $relPath = $relDir . '/' . $storedName;

        if (!@move_uploaded_file($validated['tmp_name'], $absPath)) {
            throw new RuntimeException('No se pudo guardar el archivo en disco.');
        }
        @chmod($absPath, 0644);

        $stmt = $this->db->prepare("
            INSERT INTO attachments
                (empresa_id, owner_type, owner_id, original_name, stored_name, mime, size_bytes, path, uploaded_by)
            VALUES
                (:empresa_id, :owner_type, :owner_id, :original_name, :stored_name, :mime, :size_bytes, :path, :uploaded_by)
        ");
        $stmt->execute([
            ':empresa_id'    => $empresaId,
            ':owner_type'    => $ownerType,
            ':owner_id'      => $ownerId,
            ':original_name' => $validated['original_name'],
            ':stored_name'   => $storedName,
            ':mime'          => $validated['mime'],
            ':size_bytes'    => $validated['size'],
            ':path'          => $relPath,
            ':uploaded_by'   => $uploadedBy,
        ]);

        $id = (int) $this->db->lastInsertId();

        return [
            'id'            => $id,
            'original_name' => $validated['original_name'],
            'size_bytes'    => (int) $validated['size'],
            'mime'          => $validated['mime'],
        ];
    }

    /**
     * Lista los adjuntos activos de un owner (no incluye soft-deleted).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByOwner(int $empresaId, string $ownerType, int $ownerId): array
    {
        $this->assertEmpresa($empresaId);
        $this->assertOwnerType($ownerType);

        $stmt = $this->db->prepare("
            SELECT id, original_name, stored_name, mime, size_bytes, uploaded_by, created_at
            FROM attachments
            WHERE empresa_id = :empresa_id
              AND owner_type = :owner_type
              AND owner_id = :owner_id
              AND deleted_at IS NULL
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':owner_type' => $ownerType,
            ':owner_id'   => $ownerId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Borra físicamente un adjunto (archivo + fila).
     * Valida IDOR via empresa_id.
     */
    public function delete(int $attachmentId, int $empresaId): bool
    {
        $this->assertEmpresa($empresaId);

        $stmt = $this->db->prepare("SELECT id, path FROM attachments WHERE id = :id AND empresa_id = :empresa_id LIMIT 1");
        $stmt->execute([':id' => $attachmentId, ':empresa_id' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        $abs = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $row['path']);
        if (is_file($abs)) {
            @unlink($abs);
        }

        $del = $this->db->prepare("DELETE FROM attachments WHERE id = :id AND empresa_id = :empresa_id");
        $del->execute([':id' => $attachmentId, ':empresa_id' => $empresaId]);
        return true;
    }

    /**
     * Borra TODOS los adjuntos de un owner (archivos + filas).
     * Para llamar desde el delete físico del repo padre (ej: CrmNotaRepository::forceDelete()).
     *
     * @return int Cantidad borrada.
     */
    public function deleteByOwner(int $empresaId, string $ownerType, int $ownerId): int
    {
        $this->assertEmpresa($empresaId);
        $this->assertOwnerType($ownerType);

        $stmt = $this->db->prepare("SELECT id, path FROM attachments WHERE empresa_id = :e AND owner_type = :t AND owner_id = :o");
        $stmt->execute([':e' => $empresaId, ':t' => $ownerType, ':o' => $ownerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $abs = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $row['path']);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }

        $del = $this->db->prepare("DELETE FROM attachments WHERE empresa_id = :e AND owner_type = :t AND owner_id = :o");
        $del->execute([':e' => $empresaId, ':t' => $ownerType, ':o' => $ownerId]);
        return count($rows);
    }

    /**
     * Resuelve un attachment para servirlo por el endpoint de download.
     * Valida IDOR: el registro tiene que pertenecer a la empresa del usuario.
     *
     * @return array{abs_path:string, original_name:string, mime:string, size_bytes:int}
     * @throws RuntimeException si no existe o no pertenece a la empresa.
     */
    public function getForDownload(int $attachmentId, int $empresaId): array
    {
        $this->assertEmpresa($empresaId);

        $stmt = $this->db->prepare("
            SELECT id, empresa_id, original_name, mime, size_bytes, path
            FROM attachments
            WHERE id = :id AND empresa_id = :empresa_id AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $attachmentId, ':empresa_id' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Adjunto no encontrado.');
        }

        $abs = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $row['path']);
        if (!is_file($abs)) {
            throw new RuntimeException('El archivo ya no está disponible en el servidor.');
        }

        return [
            'abs_path'      => $abs,
            'original_name' => (string) $row['original_name'],
            'mime'          => (string) $row['mime'],
            'size_bytes'    => (int) $row['size_bytes'],
        ];
    }

    /**
     * Resuelve un attachment para servirlo INLINE (preview).
     * Solo permite MIMEs que el browser puede renderizar de forma segura:
     * exclusivamente imágenes rasterizadas. SVG queda fuera (puede contener <script>).
     *
     * Si el MIME no es previewable, tira RuntimeException — el caller debe caer
     * a /download como fallback.
     *
     * @return array{abs_path:string, original_name:string, mime:string, size_bytes:int}
     */
    public function getForPreview(int $attachmentId, int $empresaId): array
    {
        $info = $this->getForDownload($attachmentId, $empresaId);

        $previewable = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp'];
        if (!in_array($info['mime'], $previewable, true)) {
            throw new RuntimeException('Preview no disponible para este tipo de archivo.');
        }
        return $info;
    }

    /**
     * Expone la config para consumo del partial / controller (máx archivos, MB, etc).
     */
    public function getLimits(): array
    {
        return [
            'max_files_per_owner'       => (int) $this->config['max_files_per_owner'],
            'max_file_size_bytes'       => (int) $this->config['max_file_size_bytes'],
            'max_total_bytes_per_owner' => (int) $this->config['max_total_bytes_per_owner'],
        ];
    }

    /* ---------------------------------------------------------------------
     * Internos
     * ------------------------------------------------------------------ */

    private function assertEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('empresa_id inválido.');
        }
    }

    private function assertOwnerType(string $ownerType): void
    {
        $allowed = $this->config['allowed_owner_types'] ?? [];
        if (!in_array($ownerType, $allowed, true)) {
            throw new InvalidArgumentException('owner_type no permitido: ' . $ownerType);
        }
    }

    private function assertQuota(int $empresaId, string $ownerType, int $ownerId, int $incomingBytes): void
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS qty, COALESCE(SUM(size_bytes), 0) AS total
            FROM attachments
            WHERE empresa_id = :e AND owner_type = :t AND owner_id = :o AND deleted_at IS NULL
        ");
        $stmt->execute([':e' => $empresaId, ':t' => $ownerType, ':o' => $ownerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $qty   = (int) ($row['qty'] ?? 0);
        $total = (int) ($row['total'] ?? 0);

        if ($qty >= (int) $this->config['max_files_per_owner']) {
            throw new RuntimeException('Se alcanzó el máximo de archivos permitidos por registro (' . $this->config['max_files_per_owner'] . ').');
        }
        if (($total + $incomingBytes) > (int) $this->config['max_total_bytes_per_owner']) {
            $maxMb = number_format(((int) $this->config['max_total_bytes_per_owner']) / (1024 * 1024), 0);
            throw new RuntimeException('El total acumulado de adjuntos superaría el máximo permitido (' . $maxMb . ' MB).');
        }
    }

    /**
     * Devuelve [abs_dir, rel_dir] para el directorio de attachments de una empresa.
     * rel_dir es relativo al BASE_PATH (guarda en DB como path).
     */
    private function buildOwnerDir(int $empresaId): array
    {
        $rel = rtrim((string) $this->config['storage_root_relative'], '/\\')
            . '/' . $empresaId
            . '/attachments/' . date('Y') . '/' . date('m');

        $abs = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        return [$abs, $rel];
    }

    /**
     * Crea un .htaccess "Require all denied" en la raíz attachments/ de la empresa
     * (una sola vez, idempotente). Fuerza que los archivos solo sean accesibles
     * por el endpoint de download — nunca por URL directa.
     */
    private function ensureDenyHtaccess(string $absMonthDir): void
    {
        // absMonthDir = .../attachments/YYYY/MM → subimos dos niveles para llegar a attachments/
        $attachmentsRoot = dirname(dirname($absMonthDir));
        if (!is_dir($attachmentsRoot)) {
            return;
        }
        $htaccess = $attachmentsRoot . DIRECTORY_SEPARATOR . '.htaccess';
        if (is_file($htaccess)) {
            return;
        }
        $content = <<<'HTACCESS'
# Archivos de adjuntos de módulos — NO servir por URL directa.
# El acceso se resuelve por el endpoint /attachments/{id}/download que
# valida IDOR (empresa_id) y fuerza Content-Disposition: attachment.

Require all denied

<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>
HTACCESS;
        @file_put_contents($htaccess, $content);
    }
}
