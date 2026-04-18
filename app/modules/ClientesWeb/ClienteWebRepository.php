<?php

declare(strict_types=1);

namespace App\Modules\ClientesWeb;

use App\Core\Database;
use App\Core\Context;
use PDO;

class ClienteWebRepository
{
    private PDO $db;
    private string $clientesTable;
    private const SEARCHABLE_FIELDS = [
        'id' => 'CAST(id AS CHAR)',
        'nombre' => 'nombre',
        'email' => 'email',
        'documento' => 'documento',
        'codigo_tango' => 'codigo_tango',
    ];

    public function __construct(string $table = 'clientes_web', bool $bootstrap = false)
    {
        $this->db = Database::getConnection();
        $this->clientesTable = preg_replace('/[^a-z0-9_]/', '', strtolower($table)) ?: 'clientes_web';
        
        if ($bootstrap) {
            $this->ensureSchema();
        }
    }

    public static function forCrm(): self
    {
        return new self('crm_clientes', true);
    }

    private function ensureSchema(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->clientesTable} (
            `id` int NOT NULL AUTO_INCREMENT,
            `empresa_id` int NOT NULL,
            `codigo_tango` varchar(15) DEFAULT NULL COMMENT 'Si es nulo se manda 000000',
            `nombre` varchar(100) NOT NULL,
            `apellido` varchar(100) NOT NULL,
            `email` varchar(150) NOT NULL,
            `email_verificado` tinyint(1) DEFAULT '0',
            `email_verificado_at` datetime DEFAULT NULL,
            `verification_token` varchar(64) DEFAULT NULL,
            `verification_expires` datetime DEFAULT NULL,
            `password_hash` varchar(255) DEFAULT NULL,
            `reset_token` varchar(64) DEFAULT NULL,
            `reset_expires` datetime DEFAULT NULL,
            `telefono` varchar(50) DEFAULT NULL,
            `documento` varchar(50) DEFAULT NULL,
            `razon_social` varchar(150) DEFAULT NULL,
            `direccion` varchar(255) DEFAULT NULL,
            `localidad` varchar(100) DEFAULT NULL,
            `provincia` varchar(100) DEFAULT NULL,
            `codigo_postal` varchar(20) DEFAULT NULL,
            `observaciones` text,
            `activo` tinyint(1) DEFAULT '1',
            `id_gva14_tango` int DEFAULT NULL,
            `id_gva01_condicion_venta` int DEFAULT NULL,
            `id_gva01_tango` int DEFAULT NULL,
            `id_gva10_lista_precios` int DEFAULT NULL,
            `id_gva10_tango` int DEFAULT NULL,
            `id_gva23_vendedor` int DEFAULT NULL,
            `id_gva23_tango` int DEFAULT NULL,
            `id_gva24_transporte` int DEFAULT NULL,
            `id_gva24_tango` int DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_emp_email_{$this->clientesTable}` (`empresa_id`,`email`),
            KEY `idx_empresa_id_{$this->clientesTable}` (`empresa_id`),
            KEY `idx_email_{$this->clientesTable}` (`email`),
            KEY `idx_documento_{$this->clientesTable}` (`documento`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
    }

    /**
     * Busca un cliente web por documento o email para una empresa.
     * Siruye para deduplicar clientes en el checkout sin exigir login previo.
     */
    public function findByDocumentoOrEmail(int $empresaId, ?string $documento, string $email): ?array
    {
        $sql = "SELECT * FROM {$this->clientesTable} 
                WHERE empresa_id = :empresa_id ";

        $params = ['empresa_id' => $empresaId];

        if (!empty($documento)) {
            $sql .= " AND (documento = :documento OR email = :email) ";
            $params['documento'] = $documento;
            $params['email'] = $email;
        } else {
            $sql .= " AND email = :email ";
            $params['email'] = $email;
        }

        $sql .= " AND activo = 1 ORDER BY id DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Crea un cliente web nuevo.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->clientesTable} (
            empresa_id, codigo_tango, nombre, apellido, email, telefono, documento, razon_social,
            direccion, localidad, provincia, codigo_postal, observaciones, activo, created_at, updated_at
        ) VALUES (
            :empresa_id, :codigo_tango, :nombre, :apellido, :email, :telefono, :documento, :razon_social,
            :direccion, :localidad, :provincia, :codigo_postal, :observaciones, 1, NOW(), NOW()
        )";

        $stmt = $this->db->prepare($sql);
        
        $params = [
            'empresa_id'    => $data['empresa_id'],
            'codigo_tango'  => $data['codigo_tango'] ?? null,
            'nombre'        => $data['nombre'],
            'apellido'      => $data['apellido'] ?? '',
            'email'         => $data['email'],
            'telefono'      => $data['telefono'] ?? null,
            'documento'     => $data['documento'] ?? null,
            'razon_social'  => $data['razon_social'] ?? null,
            'direccion'     => $data['direccion'] ?? null,
            'localidad'     => $data['localidad'] ?? null,
            'provincia'     => $data['provincia'] ?? null,
            'codigo_postal' => $data['codigo_postal'] ?? null,
            'observaciones' => $data['observaciones'] ?? null,
        ];

        $stmt->execute($params);

        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Actualiza datos de un cliente web existente (por si en un nuevo checkout cambia el teléfono o dirección)
     */
    public function updateIfChanged(int $id, array $newData): void
    {
        // Actualizamos los campos de contacto y envío
        $sql = "UPDATE {$this->clientesTable} SET 
            nombre = :nombre, 
            apellido = :apellido, 
            telefono = :telefono, 
            direccion = :direccion, 
            localidad = :localidad, 
            provincia = :provincia, 
            codigo_postal = :codigo_postal,
            razon_social = :razon_social,
            updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'empresa_id' => $newData['empresa_id'] ?? Context::getEmpresaId(),
            'nombre' => $newData['nombre'],
            'apellido' => $newData['apellido'] ?? '',
            'telefono' => $newData['telefono'] ?? null,
            'direccion' => $newData['direccion'] ?? null,
            'localidad' => $newData['localidad'] ?? null,
            'provincia' => $newData['provincia'] ?? null,
            'codigo_postal' => $newData['codigo_postal'] ?? null,
            'razon_social' => $newData['razon_social'] ?? null
        ]);
    }

    /**
     * Obtiene todos los clientes paginados para el ABM.
     */
    public function findAllPaginated(int $empresaId, int $page, int $limit, string $search, string $field, string $sort, string $dir, string $status = 'activos', array $advancedFilters = []): array
    {
        $offset = ($page - 1) * $limit;
        $orderDir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $allowedSorts = ['id', 'nombre', 'apellido', 'email', 'codigo_tango', 'id_gva14_tango', 'created_at', 'documento'];
        $orderBy = in_array(strtolower($sort), $allowedSorts) ? $sort : 'created_at';

        $activo = $status === 'papelera' ? 0 : 1;
        $sql = "SELECT * FROM {$this->clientesTable} WHERE empresa_id = :emp_id AND activo = :activo";
        $params = ['emp_id' => $empresaId, 'activo' => $activo];

        $this->applySearch($sql, $params, $search, $field, true);

        list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
            'id' => 'id',
            'nombre' => 'nombre',
            'email' => 'email',
            'documento' => 'documento',
            'codigo_tango' => 'codigo_tango',
            'id_gva14_tango' => 'id_gva14_tango',
            'created_at' => 'created_at'
        ]);
        if ($advSql !== '') {
            $sql .= ' AND (' . $advSql . ')';
            $params = array_merge($params, $advParams);
        }

        $sql .= " ORDER BY $orderBy $orderDir LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Cuenta clientes para paginación.
     */
    public function countAll(int $empresaId, string $search, string $field, string $status = 'activos', array $advancedFilters = []): int
    {
        $activo = $status === 'papelera' ? 0 : 1;
        $sql = "SELECT COUNT(*) FROM {$this->clientesTable} WHERE empresa_id = :emp_id AND activo = :activo";
        $params = ['emp_id' => $empresaId, 'activo' => $activo];
        $this->applySearch($sql, $params, $search, $field, true);

        list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
            'id' => 'id',
            'nombre' => 'nombre',
            'email' => 'email',
            'documento' => 'documento',
            'codigo_tango' => 'codigo_tango',
            'id_gva14_tango' => 'id_gva14_tango',
            'created_at' => 'created_at'
        ]);
        if ($advSql !== '') {
            $sql .= ' AND (' . $advSql . ')';
            $params = array_merge($params, $advParams);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findSuggestions(int $empresaId, string $search, string $field, int $limit = 3): array
    {
        if (trim($search) === '') {
            return [];
        }

        $sql = "SELECT id, nombre, apellido, razon_social, email, documento, codigo_tango, id_gva14_tango FROM {$this->clientesTable} WHERE empresa_id = :emp_id";
        $params = ['emp_id' => $empresaId];
        $this->applySearch($sql, $params, $search, $field, true);
        $sql .= ' ORDER BY nombre ASC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . ltrim((string) $key, ':'), $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function applySearch(string &$sql, array &$params, string $search, string $field, bool $hasWhere = false): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $operator = $hasWhere ? ' AND ' : ' WHERE ';

        if ($field !== 'all' && isset(self::SEARCHABLE_FIELDS[$field])) {
            $sql .= $operator . self::SEARCHABLE_FIELDS[$field] . ' LIKE :search';
            $params['search'] = '%' . $search . '%';
            return;
        }

        $sql .= $operator . ' (nombre LIKE :s1 OR apellido LIKE :s2 OR email LIKE :s3 OR documento LIKE :s4 OR codigo_tango LIKE :s5 OR CAST(id AS CHAR) LIKE :s6)';
        $params['s1'] = '%' . $search . '%';
        $params['s2'] = '%' . $search . '%';
        $params['s3'] = '%' . $search . '%';
        $params['s4'] = '%' . $search . '%';
        $params['s5'] = '%' . $search . '%';
        $params['s6'] = '%' . $search . '%';
    }

    /**
     * Busca cliente por ID
     */
    public function findById(int $id, int $empresaId): ?array
    {
        $sql = "SELECT * FROM {$this->clientesTable} WHERE id = :id AND empresa_id = :emp_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'emp_id' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Actualiza manualmente un cliente (ABM).
     * Scope obligatorio por empresa_id (defense-in-depth contra IDOR).
     */
    public function update(int $id, int $empresaId, array $data): void
    {
        $sql = "UPDATE {$this->clientesTable} SET
            nombre = :nombre,
            apellido = :apellido,
            email = :email,
            telefono = :telefono,
            documento = :documento,
            razon_social = :razon_social,
            direccion = :direccion,
            localidad = :localidad,
            provincia = :provincia,
            codigo_postal = :codigo_postal,
            codigo_tango = :codigo_tango,
            activo = :activo,
            updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'empresa_id' => $empresaId,
            'nombre' => $data['nombre'],
            'apellido' => $data['apellido'] ?? '',
            'email' => $data['email'],
            'telefono' => $data['telefono'] ?? null,
            'documento' => $data['documento'] ?? null,
            'razon_social' => $data['razon_social'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'localidad' => $data['localidad'] ?? null,
            'provincia' => $data['provincia'] ?? null,
            'codigo_postal' => $data['codigo_postal'] ?? null,
            'codigo_tango' => !empty($data['codigo_tango']) ? $data['codigo_tango'] : null,
            'activo' => isset($data['activo']) ? (int) $data['activo'] : 1
        ]);
    }

    public function updateTangoData(int $id, int $empresaId, array $tangoData): void
    {
        $sql = "UPDATE {$this->clientesTable} SET
            id_gva14_tango = :gva14,
            id_gva01_condicion_venta = :gva01,
            id_gva10_lista_precios = :gva10,
            id_gva23_vendedor = :gva23,
            id_gva24_transporte = :gva24,
            id_gva01_tango = :id_gva01_tango,
            id_gva10_tango = :id_gva10_tango,
            id_gva23_tango = :id_gva23_tango,
            id_gva24_tango = :id_gva24_tango,
            codigo_tango = :codigo_tango,
            updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'empresa_id' => $empresaId,
            'gva14' => $tangoData['id_gva14_tango'] ?? null,
            'gva01' => $tangoData['id_gva01_condicion_venta'] ?? null,
            'gva10' => $tangoData['id_gva10_lista_precios'] ?? null,
            'gva23' => $tangoData['id_gva23_vendedor'] ?? null,
            'gva24' => $tangoData['id_gva24_transporte'] ?? null,
            'id_gva01_tango' => $tangoData['id_gva01_tango'] ?? null,
            'id_gva10_tango' => $tangoData['id_gva10_tango'] ?? null,
            'id_gva23_tango' => $tangoData['id_gva23_tango'] ?? null,
            'id_gva24_tango' => $tangoData['id_gva24_tango'] ?? null,
            'codigo_tango' => $tangoData['codigo_tango'] ?? null
        ]);
    }

    public function clearTangoData(int $id, int $empresaId, ?string $codigoTango = null): void
    {
        $sql = "UPDATE {$this->clientesTable} SET
            id_gva14_tango = NULL,
            id_gva01_condicion_venta = NULL,
            id_gva10_lista_precios = NULL,
            id_gva23_vendedor = NULL,
            id_gva24_transporte = NULL,
            id_gva01_tango = NULL,
            id_gva10_tango = NULL,
            id_gva23_tango = NULL,
            id_gva24_tango = NULL,
            codigo_tango = :codigo_tango,
            updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'empresa_id' => $empresaId,
            'codigo_tango' => $codigoTango !== null && $codigoTango !== '' ? $codigoTango : null,
        ]);
    }

    public function updateRelacionOverrides(int $id, int $empresaId, array $data): void
    {
        $sql = "UPDATE {$this->clientesTable} SET
            id_gva01_condicion_venta = :gva01_codigo,
            id_gva10_lista_precios = :gva10_codigo,
            id_gva23_vendedor = :gva23_codigo,
            id_gva24_transporte = :gva24_codigo,
            id_gva01_tango = :gva01_id,
            id_gva10_tango = :gva10_id,
            id_gva23_tango = :gva23_id,
            id_gva24_tango = :gva24_id,
            updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'empresa_id' => $empresaId,
            'gva01_codigo' => $this->normalizeNullableInt($data['id_gva01_condicion_venta'] ?? null),
            'gva10_codigo' => $this->normalizeNullableInt($data['id_gva10_lista_precios'] ?? null),
            'gva23_codigo' => $this->normalizeNullableInt($data['id_gva23_vendedor'] ?? null),
            'gva24_codigo' => $this->normalizeNullableInt($data['id_gva24_transporte'] ?? null),
            'gva01_id' => $this->normalizeNullableInt($data['id_gva01_tango'] ?? null),
            'gva10_id' => $this->normalizeNullableInt($data['id_gva10_tango'] ?? null),
            'gva23_id' => $this->normalizeNullableInt($data['id_gva23_tango'] ?? null),
            'gva24_id' => $this->normalizeNullableInt($data['id_gva24_tango'] ?? null),
        ]);
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function softDelete(int $id, int $empresaId): void
    {
        $sql = "UPDATE {$this->clientesTable} SET activo = 0, updated_at = NOW() WHERE id = :id AND empresa_id = :emp_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'emp_id' => $empresaId]);
    }

    public function restore(int $id, int $empresaId): void
    {
        $sql = "UPDATE {$this->clientesTable} SET activo = 1, updated_at = NOW() WHERE id = :id AND empresa_id = :emp_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'emp_id' => $empresaId]);
    }

    public function forceDelete(int $id, int $empresaId): void
    {
        $sql = "DELETE FROM {$this->clientesTable} WHERE id = :id AND empresa_id = :emp_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'emp_id' => $empresaId]);
    }

    public function softDeleteBulk(array $ids, int $empresaId): void
    {
        if (empty($ids)) return;
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "UPDATE {$this->clientesTable} SET activo = 0, updated_at = NOW() WHERE empresa_id = ? AND id IN ($in)";
        $stmt = $this->db->prepare($sql);
        $params = array_merge([$empresaId], $ids);
        $stmt->execute($params);
    }

    public function restoreBulk(array $ids, int $empresaId): void
    {
        if (empty($ids)) return;
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "UPDATE {$this->clientesTable} SET activo = 1, updated_at = NOW() WHERE empresa_id = ? AND id IN ($in)";
        $stmt = $this->db->prepare($sql);
        $params = array_merge([$empresaId], $ids);
        $stmt->execute($params);
    }

    public function forceDeleteBulk(array $ids, int $empresaId): void
    {
        if (empty($ids)) return;
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "DELETE FROM {$this->clientesTable} WHERE empresa_id = ? AND id IN ($in)";
        $stmt = $this->db->prepare($sql);
        $params = array_merge([$empresaId], $ids);
        $stmt->execute($params);
    }
}
