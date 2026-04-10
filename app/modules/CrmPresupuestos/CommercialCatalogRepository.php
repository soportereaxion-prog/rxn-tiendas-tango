<?php
declare(strict_types=1);

namespace App\Modules\CrmPresupuestos;

use App\Core\Database;
use PDO;

class CommercialCatalogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ensureSchema();
    }

    public function findAllByType(int $empresaId, string $type): array
    {
        $stmt = $this->db->prepare('SELECT id, tipo, codigo, descripcion, id_interno, payload_json, fecha_ultima_sync
            FROM crm_catalogo_comercial_items
            WHERE empresa_id = :empresa_id AND tipo = :tipo
            ORDER BY descripcion ASC, codigo ASC');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':tipo' => $type,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findFirstByType(int $empresaId, string $type): ?array
    {
        $stmt = $this->db->prepare('SELECT id, tipo, codigo, descripcion, id_interno, payload_json, fecha_ultima_sync
            FROM crm_catalogo_comercial_items
            WHERE empresa_id = :empresa_id AND tipo = :tipo
            ORDER BY descripcion ASC, codigo ASC LIMIT 1');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':tipo' => $type,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findOption(int $empresaId, string $type, ?string $code): ?array
    {
        $code = trim((string) $code);
        if ($code === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, tipo, codigo, descripcion, id_interno, payload_json, fecha_ultima_sync
            FROM crm_catalogo_comercial_items
            WHERE empresa_id = :empresa_id AND tipo = :tipo AND codigo = :codigo
            LIMIT 1');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':tipo' => $type,
            ':codigo' => $code,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function countByType(int $empresaId, string $type): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM crm_catalogo_comercial_items WHERE empresa_id = :empresa_id AND tipo = :tipo');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':tipo' => $type,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function upsertMany(int $empresaId, string $type, array $items): array
    {
        $stats = [
            'inserted' => 0,
            'updated' => 0,
            'received' => 0,
        ];

        foreach ($items as $item) {
            $codigo = trim((string) ($item['codigo'] ?? ''));
            $descripcion = trim((string) ($item['descripcion'] ?? $item['label'] ?? ''));

            if ($codigo === '' || $descripcion === '') {
                continue;
            }

            $stats['received']++;
            $existing = $this->findOption($empresaId, $type, $codigo);
            $payloadJson = isset($item['payload_json'])
                ? (is_string($item['payload_json']) ? $item['payload_json'] : json_encode($item['payload_json']))
                : json_encode($item, JSON_UNESCAPED_UNICODE);

            if ($existing === null) {
                $stmt = $this->db->prepare('INSERT INTO crm_catalogo_comercial_items (
                        empresa_id, tipo, codigo, descripcion, id_interno, payload_json, fecha_ultima_sync, created_at, updated_at
                    ) VALUES (
                        :empresa_id, :tipo, :codigo, :descripcion, :id_interno, :payload_json, NOW(), NOW(), NOW()
                    )');
                $stmt->execute([
                    ':empresa_id' => $empresaId,
                    ':tipo' => $type,
                    ':codigo' => $codigo,
                    ':descripcion' => $descripcion,
                    ':id_interno' => $this->nullableInt($item['id_interno'] ?? null),
                    ':payload_json' => $payloadJson,
                ]);
                $stats['inserted']++;
                continue;
            }

            $stmt = $this->db->prepare('UPDATE crm_catalogo_comercial_items SET
                    descripcion = :descripcion,
                    id_interno = :id_interno,
                    payload_json = :payload_json,
                    fecha_ultima_sync = NOW(),
                    updated_at = NOW()
                WHERE id = :id AND empresa_id = :empresa_id');
            $stmt->execute([
                ':id' => (int) $existing['id'],
                ':empresa_id' => $empresaId,
                ':descripcion' => $descripcion,
                ':id_interno' => $this->nullableInt($item['id_interno'] ?? null),
                ':payload_json' => $payloadJson,
            ]);
            $stats['updated']++;
        }

        return $stats;
    }

    private function ensureSchema(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS crm_catalogo_comercial_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            tipo VARCHAR(40) NOT NULL,
            codigo VARCHAR(50) NOT NULL,
            descripcion VARCHAR(255) NOT NULL,
            id_interno INT NULL,
            payload_json LONGTEXT NULL,
            fecha_ultima_sync DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_crm_catalogo_comercial_empresa_tipo_codigo (empresa_id, tipo, codigo),
            KEY idx_crm_catalogo_comercial_empresa_tipo (empresa_id, tipo),
            KEY idx_crm_catalogo_comercial_empresa_tipo_desc (empresa_id, tipo, descripcion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
