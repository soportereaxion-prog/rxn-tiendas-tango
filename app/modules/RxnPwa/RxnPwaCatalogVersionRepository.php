<?php

declare(strict_types=1);

namespace App\Modules\RxnPwa;

use App\Core\Database;
use PDO;

/**
 * Versiones del catálogo offline por empresa.
 *
 * Tabla: `rxnpwa_catalog_versions` (1 fila por empresa, UNIQUE empresa_id).
 *
 * Flujo:
 *   - Sync Catálogos / Sync Artículos / Sync Clientes invalidan la versión llamando `invalidate()`.
 *   - El primer GET /api/rxnpwa/catalog/version detecta `hash IS NULL` y dispara recálculo (vía CatalogService).
 *   - Recálculos posteriores reutilizan el hash hasta la próxima invalidación.
 */
class RxnPwaCatalogVersionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS rxnpwa_catalog_versions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                empresa_id INT UNSIGNED NOT NULL,
                hash CHAR(40) NULL,
                generated_at DATETIME NULL,
                payload_size_bytes INT UNSIGNED NULL,
                payload_items_count INT UNSIGNED NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_rxnpwa_catalog_versions_empresa (empresa_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Throwable $th) {
            // Idempotente; lo crea la migración formal igual.
        }
    }

    public function findByEmpresa(int $empresaId): ?array
    {
        $stmt = $this->db->prepare('SELECT empresa_id, hash, generated_at, payload_size_bytes, payload_items_count
            FROM rxnpwa_catalog_versions WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([':empresa_id' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Persiste un hash recién calculado (UPSERT por empresa_id).
     */
    public function save(int $empresaId, string $hash, int $sizeBytes, int $itemsCount): void
    {
        $stmt = $this->db->prepare('INSERT INTO rxnpwa_catalog_versions
                (empresa_id, hash, generated_at, payload_size_bytes, payload_items_count)
            VALUES (:empresa_id, :hash, NOW(), :size, :items)
            ON DUPLICATE KEY UPDATE
                hash = VALUES(hash),
                generated_at = VALUES(generated_at),
                payload_size_bytes = VALUES(payload_size_bytes),
                payload_items_count = VALUES(payload_items_count)');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':hash' => $hash,
            ':size' => $sizeBytes,
            ':items' => $itemsCount,
        ]);
    }

    /**
     * Marca el hash como inválido (NULL). El próximo GET /version dispara recálculo.
     * Llamado desde los syncs (artículos, clientes, catálogos comerciales).
     */
    public function invalidate(int $empresaId): void
    {
        $stmt = $this->db->prepare('INSERT INTO rxnpwa_catalog_versions (empresa_id, hash, generated_at)
            VALUES (:empresa_id, NULL, NULL)
            ON DUPLICATE KEY UPDATE hash = NULL, generated_at = NULL');
        $stmt->execute([':empresa_id' => $empresaId]);
    }
}
