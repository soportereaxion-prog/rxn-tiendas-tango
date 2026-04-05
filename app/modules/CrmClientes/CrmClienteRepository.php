<?php
declare(strict_types=1);

namespace App\Modules\CrmClientes;

use App\Core\Database;
use PDO;

class CrmClienteRepository
{
    private const SEARCHABLE_FIELDS = [
        'id' => 'CAST(id AS CHAR)',
        'codigo_tango' => 'codigo_tango',
        'razon_social' => 'razon_social',
        'documento' => 'documento',
        'email' => 'email',
        'telefono' => 'telefono',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ensureSchema();
    }

    public function countAll(int $empresaId, string $search = '', string $field = 'all', bool $onlyDeleted = false, array $advancedFilters = []): int
    {
        $delCond = $onlyDeleted ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
        $sql = "SELECT COUNT(*) FROM crm_clientes WHERE empresa_id = :empresa_id AND $delCond";
        $params = [':empresa_id' => $empresaId];
        $this->applySearch($sql, $params, $search, $field, true);

        list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
            'id' => 'CAST(id AS CHAR)',
            'codigo_tango' => 'codigo_tango',
            'razon_social' => 'razon_social',
            'documento' => 'documento',
            'email' => 'email',
            'telefono' => 'telefono',
        ]);
        if ($advSql !== '') {
            $sql .= ' AND (' . $advSql . ')';
            $params = array_merge($params, $advParams);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findAllPaginated(
        int $empresaId,
        int $page = 1,
        int $limit = 50,
        string $search = '',
        string $field = 'all',
        string $sort = 'razon_social',
        string $dir = 'ASC',
        bool $onlyDeleted = false,
        array $advancedFilters = []
    ): array {
        $offset = max(0, ($page - 1) * $limit);
        $allowedSorts = ['id', 'codigo_tango', 'razon_social', 'documento', 'email', 'telefono', 'activo', 'fecha_ultima_sync', 'updated_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'razon_social';
        }

        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $delCond = $onlyDeleted ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
        $sql = "SELECT * FROM crm_clientes WHERE empresa_id = :empresa_id AND $delCond";
        $params = [':empresa_id' => $empresaId];
        $this->applySearch($sql, $params, $search, $field, true);

        list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
            'id' => 'CAST(id AS CHAR)',
            'codigo_tango' => 'codigo_tango',
            'razon_social' => 'razon_social',
            'documento' => 'documento',
            'email' => 'email',
            'telefono' => 'telefono',
        ]);
        if ($advSql !== '') {
            $sql .= ' AND (' . $advSql . ')';
            $params = array_merge($params, $advParams);
        }

        $sql .= ' ORDER BY ' . $sort . ' ' . $dir . ', razon_social ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findSuggestions(int $empresaId, string $search = '', string $field = 'all', int $limit = 3): array
    {
        if (trim($search) === '') {
            return [];
        }

        $sql = 'SELECT id, codigo_tango, razon_social, documento, email, telefono, id_gva14_tango FROM crm_clientes WHERE empresa_id = :empresa_id AND deleted_at IS NULL';
        $params = [':empresa_id' => $empresaId];
        $this->applySearch($sql, $params, $search, $field, true);
        $sql .= ' ORDER BY
            CASE
                WHEN razon_social = :o_exact1 THEN 1
                WHEN razon_social LIKE :o_start1 THEN 2
                WHEN razon_social LIKE :o_any1 THEN 3
                WHEN codigo_tango = :o_exact2 THEN 4
                WHEN codigo_tango LIKE :o_start2 THEN 5
                WHEN codigo_tango LIKE :o_any2 THEN 6
                WHEN email = :o_exact3 THEN 7
                WHEN email LIKE :o_start3 THEN 8
                WHEN email LIKE :o_any3 THEN 9
                ELSE 10
            END ASC, razon_social ASC LIMIT :limit';

        $params[':o_exact1'] = $search;
        $params[':o_start1'] = $search . '%';
        $params[':o_any1']   = '%' . $search . '%';
        $params[':o_exact2'] = $search;
        $params[':o_start2'] = $search . '%';
        $params[':o_any2']   = '%' . $search . '%';
        $params[':o_exact3'] = $search;
        $params[':o_start3'] = $search . '%';
        $params[':o_any3']   = '%' . $search . '%';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByCodigoTango(string $codigoTango, int $empresaId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_clientes WHERE codigo_tango = :codigo_tango AND empresa_id = :empresa_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([
            ':codigo_tango' => $codigoTango,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id, int $empresaId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_clientes WHERE id = :id AND empresa_id = :empresa_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(int $id, int $empresaId, array $data): bool
    {
        $stmt = $this->db->prepare('UPDATE crm_clientes SET
                razon_social = :razon_social,
                documento = :documento,
                email = :email,
                telefono = :telefono,
                direccion = :direccion,
                activo = :activo,
                updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id');

        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':razon_social' => $this->nullableString($data['razon_social'] ?? null),
            ':documento' => $this->nullableString($data['documento'] ?? null),
            ':email' => $this->nullableString($data['email'] ?? null),
            ':telefono' => $this->nullableString($data['telefono'] ?? null),
            ':direccion' => $this->nullableString($data['direccion'] ?? null),
            ':activo' => !empty($data['activo']) ? 1 : 0,
        ]);
    }

    public function truncate(int $empresaId): void
    {
        $stmt = $this->db->prepare('UPDATE crm_clientes SET deleted_at = NOW() WHERE empresa_id = :empresa_id');
        $stmt->execute([':empresa_id' => $empresaId]);
    }

    public function deleteByIds(array $ids, int $empresaId): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'UPDATE crm_clientes SET deleted_at = NOW() WHERE empresa_id = ? AND id IN (' . $placeholders . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$empresaId], array_map('intval', $ids)));

        return $stmt->rowCount();
    }

    public function restoreByIds(array $ids, int $empresaId): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'UPDATE crm_clientes SET deleted_at = NULL WHERE empresa_id = ? AND id IN (' . $placeholders . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$empresaId], array_map('intval', $ids)));

        return $stmt->rowCount();
    }

    public function forceDeleteByIds(array $ids, int $empresaId): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'DELETE FROM crm_clientes WHERE empresa_id = ? AND id IN (' . $placeholders . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$empresaId], array_map('intval', $ids)));

        return $stmt->rowCount();
    }

    public function copy(int $id, int $empresaId): void
    {
        $cliente = $this->findById($id, $empresaId);
        if (!$cliente) {
            throw new \RuntimeException('El cliente a copiar no existe o no pertenece a la empresa.');
        }

        $stmt = $this->db->prepare('INSERT INTO crm_clientes (
            empresa_id, razon_social, documento, email, telefono, direccion, activo, created_at, updated_at
        ) VALUES (
            :empresa_id, :razon_social, :documento, :email, :telefono, :direccion, :activo, NOW(), NOW()
        )');

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':razon_social' => 'Copia de ' . ($cliente['razon_social'] ?? 'Cliente sin nombre'),
            ':documento' => $this->nullableString($cliente['documento'] ?? null),
            ':email' => $this->nullableString($cliente['email'] ?? null),
            ':telefono' => $this->nullableString($cliente['telefono'] ?? null),
            ':direccion' => $this->nullableString($cliente['direccion'] ?? null),
            ':activo' => 1
        ]);
    }

    public function upsertFromTango(int $empresaId, array $data): string
    {
        $idGva14 = $this->nullableInt($data['id_gva14_tango'] ?? null);
        $codigoTango = $this->nullableString($data['codigo_tango'] ?? null);

        if ($idGva14 === null && $codigoTango === null) {
            return 'skipped';
        }

        $existing = $this->findExistingForSync($empresaId, $idGva14, $codigoTango);
        $payload = [
            ':empresa_id' => $empresaId,
            ':id_gva14_tango' => $idGva14,
            ':codigo_tango' => $codigoTango,
            ':razon_social' => $this->nullableString($data['razon_social'] ?? null),
            ':documento' => $this->nullableString($data['documento'] ?? null),
            ':email' => $this->nullableString($data['email'] ?? null),
            ':telefono' => $this->nullableString($data['telefono'] ?? null),
            ':direccion' => $this->nullableString($data['direccion'] ?? null),
            ':activo' => $this->normalizeActive($data['activo'] ?? true),
            ':fecha_ultima_sync' => date('Y-m-d H:i:s'),
            ':id_gva01_condicion_venta' => $this->nullableInt($data['id_gva01_condicion_venta'] ?? null),
            ':id_gva10_lista_precios' => $this->nullableInt($data['id_gva10_lista_precios'] ?? null),
            ':id_gva23_vendedor' => $this->nullableInt($data['id_gva23_vendedor'] ?? null),
            ':id_gva24_transporte' => $this->nullableInt($data['id_gva24_transporte'] ?? null),
            ':id_gva01_tango' => $this->nullableInt($data['id_gva01_tango'] ?? null),
            ':id_gva10_tango' => $this->nullableInt($data['id_gva10_tango'] ?? null),
            ':id_gva23_tango' => $this->nullableInt($data['id_gva23_tango'] ?? null),
            ':id_gva24_tango' => $this->nullableInt($data['id_gva24_tango'] ?? null),
        ];

        if ($existing === null) {
            $stmt = $this->db->prepare('INSERT INTO crm_clientes (
                    empresa_id, id_gva14_tango, codigo_tango, razon_social, documento, email, telefono, direccion, activo,
                    fecha_ultima_sync, id_gva01_condicion_venta, id_gva10_lista_precios, id_gva23_vendedor, id_gva24_transporte,
                    id_gva01_tango, id_gva10_tango, id_gva23_tango, id_gva24_tango, created_at, updated_at
                ) VALUES (
                    :empresa_id, :id_gva14_tango, :codigo_tango, :razon_social, :documento, :email, :telefono, :direccion, :activo,
                    :fecha_ultima_sync, :id_gva01_condicion_venta, :id_gva10_lista_precios, :id_gva23_vendedor, :id_gva24_transporte,
                    :id_gva01_tango, :id_gva10_tango, :id_gva23_tango, :id_gva24_tango, NOW(), NOW()
                )');
            $stmt->execute($payload);
            return 'inserted';
        }

        $payload[':id'] = (int) $existing['id'];
        $stmt = $this->db->prepare('UPDATE crm_clientes SET
                id_gva14_tango = :id_gva14_tango,
                codigo_tango = :codigo_tango,
                razon_social = :razon_social,
                documento = :documento,
                email = :email,
                telefono = :telefono,
                direccion = :direccion,
                activo = :activo,
                fecha_ultima_sync = :fecha_ultima_sync,
                id_gva01_condicion_venta = :id_gva01_condicion_venta,
                id_gva10_lista_precios = :id_gva10_lista_precios,
                id_gva23_vendedor = :id_gva23_vendedor,
                id_gva24_transporte = :id_gva24_transporte,
                id_gva01_tango = :id_gva01_tango,
                id_gva10_tango = :id_gva10_tango,
                id_gva23_tango = :id_gva23_tango,
                id_gva24_tango = :id_gva24_tango,
                updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id');
        $stmt->execute($payload);

        return 'updated';
    }

    private function findExistingForSync(int $empresaId, ?int $idGva14, ?string $codigoTango): ?array
    {
        if ($idGva14 !== null) {
            $stmt = $this->db->prepare('SELECT id FROM crm_clientes WHERE empresa_id = :empresa_id AND id_gva14_tango = :id_gva14 LIMIT 1');
            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':id_gva14' => $idGva14,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        if ($codigoTango !== null) {
            $stmt = $this->db->prepare('SELECT id FROM crm_clientes WHERE empresa_id = :empresa_id AND codigo_tango = :codigo_tango LIMIT 1');
            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':codigo_tango' => $codigoTango,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    private function applySearch(string &$sql, array &$params, string $search = '', string $field = 'all', bool $hasWhere = false): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $operator = $hasWhere ? ' AND ' : ' WHERE ';

        if ($field !== 'all' && isset(self::SEARCHABLE_FIELDS[$field])) {
            $sql .= $operator . self::SEARCHABLE_FIELDS[$field] . ' LIKE :search';
            $params[':search'] = '%' . $search . '%';
            return;
        }

        $conditions = [];
        foreach (self::SEARCHABLE_FIELDS as $key => $column) {
            $placeholder = ':search_' . $key;
            $conditions[] = $column . ' LIKE ' . $placeholder;
            $params[$placeholder] = '%' . $search . '%';
        }

        $sql .= $operator . '(' . implode(' OR ', $conditions) . ')';
    }

    private function ensureSchema(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS crm_clientes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            id_gva14_tango INT NULL,
            codigo_tango VARCHAR(30) NULL,
            razon_social VARCHAR(180) NOT NULL,
            documento VARCHAR(50) NULL,
            email VARCHAR(150) NULL,
            telefono VARCHAR(80) NULL,
            direccion VARCHAR(255) NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            fecha_ultima_sync DATETIME NULL,
            id_gva01_condicion_venta INT NULL,
            id_gva10_lista_precios INT NULL,
            id_gva23_vendedor INT NULL,
            id_gva24_transporte INT NULL,
            id_gva01_tango INT NULL,
            id_gva10_tango INT NULL,
            id_gva23_tango INT NULL,
            id_gva24_tango INT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_crm_clientes_empresa_id_gva14 (empresa_id, id_gva14_tango),
            UNIQUE KEY uk_crm_clientes_empresa_codigo_tango (empresa_id, codigo_tango),
            KEY idx_crm_clientes_empresa_razon (empresa_id, razon_social),
            KEY idx_crm_clientes_empresa_documento (empresa_id, documento),
            KEY idx_crm_clientes_empresa_email (empresa_id, email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->ensureColumnExists('id_gva14_tango', 'ALTER TABLE crm_clientes ADD COLUMN id_gva14_tango INT NULL AFTER empresa_id');
        $this->ensureColumnExists('codigo_tango', 'ALTER TABLE crm_clientes ADD COLUMN codigo_tango VARCHAR(30) NULL AFTER id_gva14_tango');
        $this->ensureColumnExists('razon_social', 'ALTER TABLE crm_clientes ADD COLUMN razon_social VARCHAR(180) NOT NULL DEFAULT "" AFTER codigo_tango');
        $this->ensureColumnExists('documento', 'ALTER TABLE crm_clientes ADD COLUMN documento VARCHAR(50) NULL AFTER razon_social');
        $this->ensureColumnExists('email', 'ALTER TABLE crm_clientes ADD COLUMN email VARCHAR(150) NULL AFTER documento');
        $this->ensureColumnExists('telefono', 'ALTER TABLE crm_clientes ADD COLUMN telefono VARCHAR(80) NULL AFTER email');
        $this->ensureColumnExists('direccion', 'ALTER TABLE crm_clientes ADD COLUMN direccion VARCHAR(255) NULL AFTER telefono');
        $this->ensureColumnExists('activo', 'ALTER TABLE crm_clientes ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER direccion');
        $this->ensureColumnExists('fecha_ultima_sync', 'ALTER TABLE crm_clientes ADD COLUMN fecha_ultima_sync DATETIME NULL AFTER activo');
        $this->ensureColumnExists('id_gva01_condicion_venta', 'ALTER TABLE crm_clientes ADD COLUMN id_gva01_condicion_venta INT NULL AFTER fecha_ultima_sync');
        $this->ensureColumnExists('id_gva10_lista_precios', 'ALTER TABLE crm_clientes ADD COLUMN id_gva10_lista_precios INT NULL AFTER id_gva01_condicion_venta');
        $this->ensureColumnExists('id_gva23_vendedor', 'ALTER TABLE crm_clientes ADD COLUMN id_gva23_vendedor INT NULL AFTER id_gva10_lista_precios');
        $this->ensureColumnExists('id_gva24_transporte', 'ALTER TABLE crm_clientes ADD COLUMN id_gva24_transporte INT NULL AFTER id_gva23_vendedor');
        $this->ensureColumnExists('id_gva01_tango', 'ALTER TABLE crm_clientes ADD COLUMN id_gva01_tango INT NULL AFTER id_gva24_transporte');
        $this->ensureColumnExists('id_gva10_tango', 'ALTER TABLE crm_clientes ADD COLUMN id_gva10_tango INT NULL AFTER id_gva01_tango');
        $this->ensureColumnExists('id_gva23_tango', 'ALTER TABLE crm_clientes ADD COLUMN id_gva23_tango INT NULL AFTER id_gva10_tango');
        $this->ensureColumnExists('id_gva24_tango', 'ALTER TABLE crm_clientes ADD COLUMN id_gva24_tango INT NULL AFTER id_gva23_tango');

        if ($this->columnExists('nombre') && $this->columnExists('apellido')) {
            $this->db->exec('UPDATE crm_clientes
                SET razon_social = COALESCE(NULLIF(razon_social, ""), NULLIF(TRIM(CONCAT(COALESCE(nombre, ""), " ", COALESCE(apellido, ""))), ""), CONCAT("Cliente #", id))');
        } else {
            $this->db->exec('UPDATE crm_clientes SET razon_social = COALESCE(NULLIF(razon_social, ""), CONCAT("Cliente #", id))');
        }

        if ($this->columnExists('cuit')) {
            $this->db->exec('UPDATE crm_clientes SET documento = COALESCE(documento, cuit) WHERE documento IS NULL');
        }

        if ($this->columnExists('domicilio')) {
            $this->db->exec('UPDATE crm_clientes SET direccion = COALESCE(direccion, domicilio) WHERE direccion IS NULL');
        }

        $this->db->exec('UPDATE crm_clientes SET fecha_ultima_sync = COALESCE(fecha_ultima_sync, updated_at, created_at) WHERE fecha_ultima_sync IS NULL');
        $this->ensureIndexExists('uk_crm_clientes_empresa_id_gva14', 'ALTER TABLE crm_clientes ADD KEY uk_crm_clientes_empresa_id_gva14 (empresa_id, id_gva14_tango)');
        $this->ensureIndexExists('uk_crm_clientes_empresa_codigo_tango', 'ALTER TABLE crm_clientes ADD KEY uk_crm_clientes_empresa_codigo_tango (empresa_id, codigo_tango)');
        $this->ensureIndexExists('idx_crm_clientes_empresa_razon', 'ALTER TABLE crm_clientes ADD KEY idx_crm_clientes_empresa_razon (empresa_id, razon_social)');
        $this->dropIndexIfExists('uq_emp_email_crm_clientes');
        
        try {
            $this->db->exec('ALTER TABLE crm_clientes MODIFY email VARCHAR(150) NULL');
        } catch (\PDOException $e) { }
    }

    private function ensureColumnExists(string $columnName, string $alterSql): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
        $stmt->execute([
            ':table_name' => 'crm_clientes',
            ':column_name' => $columnName,
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            $this->db->exec($alterSql);
        }
    }

    private function ensureIndexExists(string $indexName, string $alterSql): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name');
        $stmt->execute([
            ':table_name' => 'crm_clientes',
            ':index_name' => $indexName,
        ]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->db->exec($alterSql);
        }
    }

    private function dropIndexIfExists(string $indexName): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name');
        $stmt->execute([
            ':table_name' => 'crm_clientes',
            ':index_name' => $indexName,
        ]);
        if ((int) $stmt->fetchColumn() > 0) {
            $this->db->exec('ALTER TABLE crm_clientes DROP INDEX ' . $indexName);
        }
    }

    private function columnExists(string $columnName): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
        $stmt->execute([
            ':table_name' => 'crm_clientes',
            ':column_name' => $columnName,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeActive(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '' || in_array($normalized, ['1', 'true', 's', 'si', 'y', 'yes', 'activo'], true)) {
            return 1;
        }

        return in_array($normalized, ['0', 'false', 'n', 'no', 'inactivo'], true) ? 0 : 1;
    }
}
