<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Database;
use PDO;

class UsuarioRepository
{
    private PDO $db;
    private const SORTABLE_FIELDS = [
        'id' => 'id',
        'nombre' => 'nombre',
        'email' => 'email',
        'es_admin' => 'es_admin',
        'activo' => 'activo',
    ];
    private const SEARCHABLE_FIELDS = [
        'id' => 'CAST(id AS CHAR)',
        'nombre' => 'nombre',
        'email' => 'email',
    ];

    public function __construct()
    {
        $this->db = Database::getConnection();
        
        try { $this->db->exec('ALTER TABLE usuarios ADD COLUMN tango_perfil_pedido_id INT NULL AFTER verification_expires;'); } catch (\Throwable $e) {}
        try { $this->db->exec('ALTER TABLE usuarios ADD COLUMN tango_perfil_pedido_codigo VARCHAR(50) NULL AFTER tango_perfil_pedido_id;'); } catch (\Throwable $e) {}
        try { $this->db->exec('ALTER TABLE usuarios ADD COLUMN tango_perfil_pedido_nombre VARCHAR(150) NULL AFTER tango_perfil_pedido_codigo;'); } catch (\Throwable $e) {}
        try { $this->db->exec('ALTER TABLE usuarios ADD COLUMN tango_perfil_snapshot_json LONGTEXT NULL AFTER tango_perfil_pedido_nombre;'); } catch (\Throwable $e) {}
        try { $this->db->exec('ALTER TABLE usuarios ADD COLUMN tango_perfil_snapshot_date DATETIME NULL AFTER tango_perfil_snapshot_json;'); } catch (\Throwable $e) {}
        try { $this->db->exec('ALTER TABLE usuarios ADD COLUMN es_rxn_admin TINYINT(1) DEFAULT 0 AFTER es_admin;'); } catch (\Throwable $e) {}
    }

    public function findByEmail(string $email, ?int $excludeId = null): ?Usuario
    {
        $sql = "SELECT * FROM usuarios WHERE email = :email AND deleted_at IS NULL";
        $params = [':email' => $email];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetchObject(Usuario::class);
        return $user ?: null;
    }

    public function findByAnuraInterno(string $anuraInterno, int $empresaId, ?int $excludeId = null): ?Usuario
    {
        $sql = "SELECT id, nombre FROM usuarios WHERE anura_interno = :anura_interno AND empresa_id = :empresa_id AND deleted_at IS NULL";
        $params = [':anura_interno' => $anuraInterno, ':empresa_id' => $empresaId];
        
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetchObject(Usuario::class);
        return $user ?: null;
    }

    public function findAllByEmpresaId(int $empresaId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE empresa_id = :empresa_id AND deleted_at IS NULL ORDER BY id DESC");
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_CLASS, Usuario::class);
    }

    public function findByIdAndEmpresaId(int $id, int $empresaId): ?Usuario
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = :id AND empresa_id = :empresa_id AND deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        $user = $stmt->fetchObject(Usuario::class);
        return $user ?: null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM usuarios WHERE deleted_at IS NULL ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_CLASS, Usuario::class);
    }

    public function countAll(bool $onlyDeleted = false): int
    {
        $delCond = $onlyDeleted ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
        return (int) $this->db->query("SELECT COUNT(*) FROM usuarios WHERE $delCond")->fetchColumn();
    }

    public function countAllByEmpresaId(int $empresaId, bool $onlyDeleted = false): int
    {
        $delCond = $onlyDeleted ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE empresa_id = :empresa_id AND $delCond");
        $stmt->execute([':empresa_id' => $empresaId]);

        return (int) $stmt->fetchColumn();
    }

    public function findFilteredPaginated(
        ?string $search = null,
        string $field = 'all',
        string $sort = 'id',
        string $dir = 'desc',
        int $limit = 10,
        int $offset = 0,
        bool $onlyDeleted = false,
        array $advancedFilters = []
    ): array {
        $delCond = $onlyDeleted ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
        $sql = "SELECT * FROM usuarios WHERE $delCond";
        $params = [];
        $this->applySearch($sql, $params, $search, $field, true);

        list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
            'nombre' => 'nombre',
            'email' => 'email',
            'es_admin' => 'CAST(es_admin AS CHAR)',
            'es_rxn_admin' => 'CAST(es_rxn_admin AS CHAR)',
            'activo' => 'CAST(activo AS CHAR)',
        ]);
        if ($advSql !== '') {
            $sql .= ' AND (' . $advSql . ')';
            $params = array_merge($params, $advParams);
        }
        $sql .= sprintf(
            ' ORDER BY %s %s LIMIT :limit OFFSET :offset',
            $this->normalizeSortField($sort),
            $this->normalizeSortDirection($dir)
        );

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_CLASS, Usuario::class);
    }

    public function countFiltered(?string $search = null, string $field = 'all', bool $onlyDeleted = false, array $advancedFilters = []): int
    {
        $delCond = $onlyDeleted ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
        $sql = "SELECT COUNT(*) FROM usuarios WHERE $delCond";
        $params = [];
        $this->applySearch($sql, $params, $search, $field, true);

        list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
            'nombre' => 'nombre',
            'email' => 'email',
            'es_admin' => 'CAST(es_admin AS CHAR)',
            'es_rxn_admin' => 'CAST(es_rxn_admin AS CHAR)',
            'activo' => 'CAST(activo AS CHAR)',
        ]);
        if ($advSql !== '') {
            $sql .= ' AND (' . $advSql . ')';
            $params = array_merge($params, $advParams);
        }

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findFilteredPaginatedByEmpresaId(
        int $empresaId,
        ?string $search = null,
        string $field = 'all',
        string $sort = 'id',
        string $dir = 'desc',
        int $limit = 10,
        int $offset = 0,
        bool $onlyDeleted = false,
        array $advancedFilters = []
    ): array {
        $delCond = $onlyDeleted ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
        $sql = "SELECT * FROM usuarios WHERE empresa_id = :empresa_id AND $delCond";
        $params = [':empresa_id' => $empresaId];
        $this->applySearch($sql, $params, $search, $field, true);

        list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
            'nombre' => 'nombre',
            'email' => 'email',
            'es_admin' => 'CAST(es_admin AS CHAR)',
            'es_rxn_admin' => 'CAST(es_rxn_admin AS CHAR)',
            'activo' => 'CAST(activo AS CHAR)',
        ]);
        if ($advSql !== '') {
            $sql .= ' AND (' . $advSql . ')';
            $params = array_merge($params, $advParams);
        }
        $sql .= sprintf(
            ' ORDER BY %s %s LIMIT :limit OFFSET :offset',
            $this->normalizeSortField($sort),
            $this->normalizeSortDirection($dir)
        );

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_CLASS, Usuario::class);
    }

    public function countFilteredByEmpresaId(int $empresaId, ?string $search = null, string $field = 'all', bool $onlyDeleted = false, array $advancedFilters = []): int
    {
        $delCond = $onlyDeleted ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
        $sql = "SELECT COUNT(*) FROM usuarios WHERE empresa_id = :empresa_id AND $delCond";
        $params = [':empresa_id' => $empresaId];
        $this->applySearch($sql, $params, $search, $field, true);

        list($advSql, $advParams) = \App\Core\AdvancedQueryFilter::build($advancedFilters, [
            'nombre' => 'nombre',
            'email' => 'email',
            'es_admin' => 'CAST(es_admin AS CHAR)',
            'es_rxn_admin' => 'CAST(es_rxn_admin AS CHAR)',
            'activo' => 'CAST(activo AS CHAR)',
        ]);
        if ($advSql !== '') {
            $sql .= ' AND (' . $advSql . ')';
            $params = array_merge($params, $advParams);
        }

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?Usuario
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetchObject(Usuario::class);
        return $user ?: null;
    }

    public function save(Usuario $usuario): void
    {
        if ($usuario->id) {
            // ROOT CAUSE FIX (release 1.29.x): el WHERE original tenía
            //   WHERE id = :id AND empresa_id = :empresa_id
            // y como el form de editar (vista superadmin) puede mandar un
            // empresa_id mal seleccionado en el dropdown "Transferir de Empresa",
            // el UPDATE matcheaba 0 filas y aparentaba haber guardado.
            // Síntoma: cambio de password "exitoso" pero login falla porque el
            // hash en DB nunca cambió.
            //
            // Fix: el aislamiento de tenant se hace en UsuarioService::getByIdForContext()
            // (el portero de carga). Acá el WHERE solo necesita la PK; y movemos
            // empresa_id al SET para que un superadmin pueda transferir un usuario
            // de una empresa a otra de forma legítima cuando lo intenta.
            $sql = "UPDATE usuarios SET
                    empresa_id = :empresa_id,
                    nombre = :nombre,
                    email = :email,
                    password_hash = :password_hash,
                    activo = :activo,
                    es_admin = :es_admin,
                    anura_interno = :anura_interno,
                    tango_perfil_pedido_id = :tango_perfil_pedido_id,
                    tango_perfil_pedido_codigo = :tango_perfil_pedido_codigo,
                    tango_perfil_pedido_nombre = :tango_perfil_pedido_nombre,
                    tango_perfil_snapshot_json = :tango_perfil_snapshot_json,
                    tango_perfil_snapshot_date = :tango_perfil_snapshot_date,
                    es_rxn_admin = :es_rxn_admin,
                    email_verificado = :email_verificado,
                    verification_token = :verification_token,
                    verification_expires = :verification_expires
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nombre' => $usuario->nombre,
                ':email' => $usuario->email,
                ':password_hash' => $usuario->password_hash,
                ':activo' => $usuario->activo,
                ':es_admin' => $usuario->es_admin,
                ':anura_interno' => $usuario->anura_interno ?? null,
                ':tango_perfil_pedido_id' => $usuario->tango_perfil_pedido_id ?? null,
                ':tango_perfil_pedido_codigo' => $usuario->tango_perfil_pedido_codigo ?? null,
                ':tango_perfil_pedido_nombre' => $usuario->tango_perfil_pedido_nombre ?? null,
                ':tango_perfil_snapshot_json' => $usuario->tango_perfil_snapshot_json ?? null,
                ':tango_perfil_snapshot_date' => $usuario->tango_perfil_snapshot_date ?? null,
                ':es_rxn_admin' => $usuario->es_rxn_admin ?? 0,
                ':email_verificado' => $usuario->email_verificado ?? 0,
                ':verification_token' => $usuario->verification_token ?? null,
                ':verification_expires' => $usuario->verification_expires ?? null,
                ':id' => $usuario->id,
                ':empresa_id' => $usuario->empresa_id
            ]);

            // Defensa: nunca dejar pasar un UPDATE silencioso. Si rowCount=0
            // tira excepción visible al usuario en lugar de "Actualizado" mentiroso.
            $affected = $stmt->rowCount();
            error_log('[UsuarioRepository::save] UPDATE usuario #' . (int) $usuario->id . ' affectedRows=' . $affected);
            if ($affected === 0) {
                throw new \RuntimeException('No se actualizó ninguna fila de usuarios (id #' . (int) $usuario->id . '). Verificá que el usuario exista.');
            }
        } else {
            $sql = "INSERT INTO usuarios (empresa_id, nombre, email, password_hash, activo, es_admin, email_verificado, verification_token, verification_expires, tango_perfil_pedido_id, tango_perfil_pedido_codigo, tango_perfil_pedido_nombre, tango_perfil_snapshot_json, tango_perfil_snapshot_date, anura_interno, es_rxn_admin) 
                    VALUES (:empresa_id, :nombre, :email, :password_hash, :activo, :es_admin, :email_verificado, :verification_token, :verification_expires, :tango_perfil_pedido_id, :tango_perfil_pedido_codigo, :tango_perfil_pedido_nombre, :tango_perfil_snapshot_json, :tango_perfil_snapshot_date, :anura_interno, :es_rxn_admin)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':empresa_id' => $usuario->empresa_id,
                ':nombre' => $usuario->nombre,
                ':email' => $usuario->email,
                ':password_hash' => $usuario->password_hash,
                ':activo' => $usuario->activo,
                ':es_admin' => $usuario->es_admin,
                ':anura_interno' => $usuario->anura_interno ?? null,
                ':email_verificado' => current([$usuario->email_verificado ?? 0]),
                ':verification_token' => $usuario->verification_token ?? null,
                ':verification_expires' => $usuario->verification_expires ?? null,
                ':tango_perfil_pedido_id' => $usuario->tango_perfil_pedido_id ?? null,
                ':tango_perfil_pedido_codigo' => $usuario->tango_perfil_pedido_codigo ?? null,
                ':tango_perfil_pedido_nombre' => $usuario->tango_perfil_pedido_nombre ?? null,
                ':tango_perfil_snapshot_json' => $usuario->tango_perfil_snapshot_json ?? null,
                ':tango_perfil_snapshot_date' => $usuario->tango_perfil_snapshot_date ?? null,
                ':es_rxn_admin' => $usuario->es_rxn_admin ?? 0
            ]);
            $usuario->id = (int) $this->db->lastInsertId();
        }
    }

    public function deleteById(int $id): void
    {
        // Add a safety check to prevent deleting the last global admin
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE es_admin = 1 AND empresa_id = 1 AND id != :id");
        $stmt->execute([':id' => $id]);
        $remainingAdmins = (int)$stmt->fetchColumn();

        $stmtCheck = $this->db->prepare("SELECT es_admin, empresa_id FROM usuarios WHERE id = :id");
        $stmtCheck->execute([':id' => $id]);
        $target = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($target && $target['es_admin'] == 1 && $target['empresa_id'] == 1 && $remainingAdmins === 0) {
            throw new \RuntimeException('No podes eliminar al último administrador global del sistema.');
        }

        $stmt = $this->db->prepare("UPDATE usuarios SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function restoreById(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE usuarios SET deleted_at = NULL WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function forceDeleteById(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function findSuggestions(?string $search = null, string $field = 'all', int $limit = 3): array
    {
        $search = trim((string) $search);

        if ($search === '') {
            return [];
        }

        $sql = 'SELECT id, nombre, email FROM usuarios WHERE deleted_at IS NULL';
        $params = [];
        $this->applySearch($sql, $params, $search, $field, true);
        $sql .= ' ORDER BY nombre ASC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findSuggestionsByEmpresaId(int $empresaId, ?string $search = null, string $field = 'all', int $limit = 3): array
    {
        $search = trim((string) $search);

        if ($search === '') {
            return [];
        }

        $sql = 'SELECT id, nombre, email FROM usuarios WHERE empresa_id = :empresa_id AND deleted_at IS NULL';
        $params = [':empresa_id' => $empresaId];
        $this->applySearch($sql, $params, $search, $field, true);
        $sql .= ' ORDER BY nombre ASC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function applySearch(string &$sql, array &$params, ?string $search, string $field = 'all', bool $hasWhere = false): void
    {
        $search = trim((string) $search);

        if ($search === '') {
            return;
        }

        $operator = $hasWhere ? ' AND ' : ' WHERE ';

        if ($field !== 'all' && isset(self::SEARCHABLE_FIELDS[$field])) {
            $params[':search'] = '%' . $search . '%';
            $sql .= $operator . '(' . self::SEARCHABLE_FIELDS[$field] . ' LIKE :search)';
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

    private function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    private function normalizeSortField(string $field): string
    {
        return self::SORTABLE_FIELDS[$field] ?? self::SORTABLE_FIELDS['id'];
    }

    private function normalizeSortDirection(string $direction): string
    {
        return strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
    }
}
