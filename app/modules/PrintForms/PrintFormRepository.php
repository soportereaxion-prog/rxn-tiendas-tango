<?php
declare(strict_types=1);

namespace App\Modules\PrintForms;

use App\Core\Database;
use PDO;

class PrintFormRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ensureSchema();
    }

    public function findDefinitionByDocumentKey(int $empresaId, string $documentKey): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM print_form_definitions WHERE empresa_id = :empresa_id AND document_key = :document_key LIMIT 1');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':document_key' => $documentKey,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDefinitionsByEmpresaId(int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT id, document_key, nombre FROM print_form_definitions WHERE empresa_id = :empresa_id AND estado = "activo" ORDER BY nombre ASC');
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findActiveVersionForDocument(int $empresaId, string $documentKey): ?array
    {
        $definition = $this->findDefinitionByDocumentKey($empresaId, $documentKey);
        if ($definition === null || empty($definition['version_activa_id'])) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT v.*, a.ruta AS background_ruta, a.mime_type AS background_mime_type, a.nombre_original AS background_nombre
            FROM print_form_versions v
            LEFT JOIN print_form_assets a ON a.id = v.background_asset_id
            WHERE v.id = :id AND v.form_definition_id = :form_definition_id
            LIMIT 1');
        $stmt->execute([
            ':id' => (int) $definition['version_activa_id'],
            ':form_definition_id' => (int) $definition['id'],
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['definition'] = $definition;
        return $row;
    }

    public function findDefinitionById(int $empresaId, int $definitionId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM print_form_definitions WHERE empresa_id = :empresa_id AND id = :id LIMIT 1');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $definitionId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findActiveVersionByDefinitionId(int $empresaId, int $definitionId): ?array
    {
        $definition = $this->findDefinitionById($empresaId, $definitionId);
        if ($definition === null || empty($definition['version_activa_id'])) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT v.*, a.ruta AS background_ruta, a.mime_type AS background_mime_type, a.nombre_original AS background_nombre
            FROM print_form_versions v
            LEFT JOIN print_form_assets a ON a.id = v.background_asset_id
            WHERE v.id = :id AND v.form_definition_id = :form_definition_id
            LIMIT 1');
        $stmt->execute([
            ':id' => (int) $definition['version_activa_id'],
            ':form_definition_id' => (int) $definition['id'],
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['definition'] = $definition;
        return $row;
    }

    public function resolveTemplateByDefinitionId(int $empresaId, int $definitionId): array
    {
        $activeVersion = $this->findActiveVersionByDefinitionId($empresaId, $definitionId);
        if ($activeVersion === null) {
            throw new \RuntimeException('No se encontró una versión activa para la plantilla seleccionada.');
        }

        $documentKey = $activeVersion['definition']['document_key'];
        $document = PrintFormRegistry::document($documentKey);

        return [
            'document' => $document,
            'definition' => $activeVersion['definition'],
            'active_version' => $activeVersion,
            'page_config' => $this->decodeJsonArray($activeVersion['page_config_json'] ?? null, $document['default_page_config'] ?? []),
            'objects' => $this->decodeJsonArray($activeVersion['objects_json'] ?? null, $document['default_objects'] ?? []),
            'fonts' => $this->decodeJsonArray($activeVersion['fonts_json'] ?? null, ['used' => []]),
            'background_url' => !empty($activeVersion['background_ruta']) ? '/rxnTiendasIA/public' . (string) $activeVersion['background_ruta'] : '',
        ];
    }

    public function resolveTemplateForDocument(int $empresaId, string $documentKey): array
    {
        $document = PrintFormRegistry::document($documentKey);
        if ($document === null) {
            throw new \RuntimeException('No existe definicion registrada para el documento solicitado.');
        }

        $activeVersion = $this->findActiveVersionForDocument($empresaId, $documentKey);

        return [
            'document' => $document,
            'definition' => $activeVersion['definition'] ?? $this->findDefinitionByDocumentKey($empresaId, $documentKey),
            'active_version' => $activeVersion,
            'page_config' => $this->decodeJsonArray($activeVersion['page_config_json'] ?? null, $document['default_page_config'] ?? []),
            'objects' => $this->decodeJsonArray($activeVersion['objects_json'] ?? null, $document['default_objects'] ?? []),
            'fonts' => $this->decodeJsonArray($activeVersion['fonts_json'] ?? null, ['used' => []]),
            'background_url' => !empty($activeVersion['background_ruta']) ? '/rxnTiendasIA/public' . (string) $activeVersion['background_ruta'] : '',
        ];
    }

    public function findVersionsByDefinitionId(int $definitionId, int $limit = 10): array
    {
        $stmt = $this->db->prepare('SELECT v.*, a.nombre_original AS background_nombre
            FROM print_form_versions v
            LEFT JOIN print_form_assets a ON a.id = v.background_asset_id
            WHERE v.form_definition_id = :form_definition_id
            ORDER BY v.version DESC
            LIMIT :limit');
        $stmt->bindValue(':form_definition_id', $definitionId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveVersion(
        int $empresaId,
        string $documentKey,
        string $name,
        ?string $description,
        string $pageConfigJson,
        string $objectsJson,
        string $fontsJson,
        ?int $backgroundAssetId,
        ?int $createdBy,
        ?string $notes = null
    ): array {
        $this->db->beginTransaction();

        try {
            $definition = $this->findDefinitionByDocumentKey($empresaId, $documentKey);

            if ($definition === null) {
                $stmt = $this->db->prepare('INSERT INTO print_form_definitions (
                        empresa_id, document_key, nombre, descripcion, estado, version_activa_id, created_at, updated_at
                    ) VALUES (
                        :empresa_id, :document_key, :nombre, :descripcion, "activo", NULL, NOW(), NOW()
                    )');
                $stmt->execute([
                    ':empresa_id' => $empresaId,
                    ':document_key' => $documentKey,
                    ':nombre' => $name,
                    ':descripcion' => $description,
                ]);

                $definitionId = (int) $this->db->lastInsertId();
                $nextVersion = 1;
            } else {
                $definitionId = (int) $definition['id'];
                $nextVersion = $this->nextVersionNumber($definitionId);

                $updateStmt = $this->db->prepare('UPDATE print_form_definitions SET nombre = :nombre, descripcion = :descripcion, updated_at = NOW() WHERE id = :id');
                $updateStmt->execute([
                    ':id' => $definitionId,
                    ':nombre' => $name,
                    ':descripcion' => $description,
                ]);
            }

            $stmt = $this->db->prepare('INSERT INTO print_form_versions (
                    form_definition_id, version, page_config_json, objects_json, fonts_json, background_asset_id, notes, created_by, created_at
                ) VALUES (
                    :form_definition_id, :version, :page_config_json, :objects_json, :fonts_json, :background_asset_id, :notes, :created_by, NOW()
                )');
            $stmt->execute([
                ':form_definition_id' => $definitionId,
                ':version' => $nextVersion,
                ':page_config_json' => $pageConfigJson,
                ':objects_json' => $objectsJson,
                ':fonts_json' => $fontsJson,
                ':background_asset_id' => $backgroundAssetId,
                ':notes' => $notes,
                ':created_by' => $createdBy,
            ]);

            $versionId = (int) $this->db->lastInsertId();

            $updateActiveStmt = $this->db->prepare('UPDATE print_form_definitions SET version_activa_id = :version_id, updated_at = NOW() WHERE id = :id');
            $updateActiveStmt->execute([
                ':version_id' => $versionId,
                ':id' => $definitionId,
            ]);

            $this->db->commit();

            return [
                'definition_id' => $definitionId,
                'version_id' => $versionId,
                'version_number' => $nextVersion,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function createAsset(int $empresaId, string $type, string $originalName, string $path, string $mimeType, int $size, ?string $metadataJson = null): int
    {
        $stmt = $this->db->prepare('INSERT INTO print_form_assets (
                empresa_id, tipo, nombre_original, ruta, mime_type, tamano, metadata_json, created_at
            ) VALUES (
                :empresa_id, :tipo, :nombre_original, :ruta, :mime_type, :tamano, :metadata_json, NOW()
            )');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':tipo' => $type,
            ':nombre_original' => $originalName,
            ':ruta' => $path,
            ':mime_type' => $mimeType,
            ':tamano' => $size,
            ':metadata_json' => $metadataJson,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findAssetById(int $assetId, int $empresaId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM print_form_assets WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':id' => $assetId,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function nextVersionNumber(int $definitionId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(version), 0) + 1 FROM print_form_versions WHERE form_definition_id = :form_definition_id');
        $stmt->execute([':form_definition_id' => $definitionId]);

        return max(1, (int) $stmt->fetchColumn());
    }

    private function decodeJsonArray(?string $json, array $fallback): array
    {
        if (!is_string($json) || trim($json) === '') {
            return $fallback;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : $fallback;
    }

    private function ensureSchema(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS print_form_definitions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            empresa_id BIGINT UNSIGNED NOT NULL,
            document_key VARCHAR(80) NOT NULL,
            nombre VARCHAR(150) NOT NULL,
            descripcion VARCHAR(255) NULL,
            estado VARCHAR(20) NOT NULL DEFAULT "activo",
            version_activa_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_print_form_empresa_document_key_nombre (empresa_id, document_key, nombre),
            KEY idx_print_form_empresa_document_key (empresa_id, document_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->db->exec('CREATE TABLE IF NOT EXISTS print_form_versions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_definition_id BIGINT UNSIGNED NOT NULL,
            version INT UNSIGNED NOT NULL,
            page_config_json LONGTEXT NOT NULL,
            objects_json LONGTEXT NOT NULL,
            fonts_json LONGTEXT NULL,
            background_asset_id BIGINT UNSIGNED NULL,
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_print_form_version (form_definition_id, version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->db->exec('CREATE TABLE IF NOT EXISTS print_form_assets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            empresa_id BIGINT UNSIGNED NOT NULL,
            tipo VARCHAR(30) NOT NULL,
            nombre_original VARCHAR(255) NOT NULL,
            ruta VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            tamano BIGINT UNSIGNED NOT NULL DEFAULT 0,
            metadata_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_print_form_assets_empresa_tipo (empresa_id, tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
}
