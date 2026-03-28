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
    }

    public function findByEmail(string $email, ?int $excludeId = null): ?Usuario
    {
        $sql = "SELECT * FROM usuarios WHERE email = :email";
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

    public function findAllByEmpresaId(int $empresaId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE empresa_id = :empresa_id ORDER BY id DESC");
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_CLASS, Usuario::class);
    }

    public function findByIdAndEmpresaId(int $id, int $empresaId): ?Usuario
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        $user = $stmt->fetchObject(Usuario::class);
        return $user ?: null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM usuarios ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_CLASS, Usuario::class);
    }

    public function countAll(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    }

    public function countAllByEmpresaId(int $empresaId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM usuarios WHERE empresa_id = :empresa_id');
        $stmt->execute([':empresa_id' => $empresaId]);

        return (int) $stmt->fetchColumn();
    }

    public function findFilteredPaginated(
        ?string $search = null,
        string $field = 'all',
        string $sort = 'id',
        string $dir = 'desc',
        int $limit = 10,
        int $offset = 0
    ): array {
        $sql = 'SELECT * FROM usuarios';
        $params = [];
        $this->applySearch($sql, $params, $search, $field);
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

    public function countFiltered(?string $search = null, string $field = 'all'): int
    {
        $sql = 'SELECT COUNT(*) FROM usuarios';
        $params = [];
        $this->applySearch($sql, $params, $search, $field);

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
        int $offset = 0
    ): array {
        $sql = 'SELECT * FROM usuarios WHERE empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];
        $this->applySearch($sql, $params, $search, $field, true);
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

    public function countFilteredByEmpresaId(int $empresaId, ?string $search = null, string $field = 'all'): int
    {
        $sql = 'SELECT COUNT(*) FROM usuarios WHERE empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];
        $this->applySearch($sql, $params, $search, $field, true);

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?Usuario
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetchObject(Usuario::class);
        return $user ?: null;
    }

    public function save(Usuario $usuario): void
    {
        if ($usuario->id) {
            $sql = "UPDATE usuarios SET 
                    nombre = :nombre,
                    email = :email,
                    password_hash = :password_hash,
                    activo = :activo,
                    es_admin = :es_admin
                    WHERE id = :id AND empresa_id = :empresa_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nombre' => $usuario->nombre,
                ':email' => $usuario->email,
                ':password_hash' => $usuario->password_hash,
                ':activo' => $usuario->activo,
                ':es_admin' => $usuario->es_admin,
                ':id' => $usuario->id,
                ':empresa_id' => $usuario->empresa_id
            ]);
        } else {
            $sql = "INSERT INTO usuarios (empresa_id, nombre, email, password_hash, activo, es_admin, email_verificado, verification_token, verification_expires) 
                    VALUES (:empresa_id, :nombre, :email, :password_hash, :activo, :es_admin, :email_verificado, :verification_token, :verification_expires)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':empresa_id' => $usuario->empresa_id,
                ':nombre' => $usuario->nombre,
                ':email' => $usuario->email,
                ':password_hash' => $usuario->password_hash,
                ':activo' => $usuario->activo,
                ':es_admin' => $usuario->es_admin,
                ':email_verificado' => current([$usuario->email_verificado ?? 0]),
                ':verification_token' => $usuario->verification_token ?? null,
                ':verification_expires' => $usuario->verification_expires ?? null
            ]);
            $usuario->id = (int) $this->db->lastInsertId();
        }
    }

    public function findSuggestions(?string $search = null, string $field = 'all', int $limit = 3): array
    {
        $search = trim((string) $search);

        if ($search === '') {
            return [];
        }

        $sql = 'SELECT id, nombre, email FROM usuarios';
        $params = [];
        $this->applySearch($sql, $params, $search, $field);
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

        $sql = 'SELECT id, nombre, email FROM usuarios WHERE empresa_id = :empresa_id';
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
