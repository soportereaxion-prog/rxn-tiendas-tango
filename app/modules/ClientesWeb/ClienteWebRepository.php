<?php

declare(strict_types=1);

namespace App\Modules\ClientesWeb;

use App\Core\Database;
use PDO;

class ClienteWebRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Busca un cliente web por documento o email para una empresa.
     * Siruye para deduplicar clientes en el checkout sin exigir login previo.
     */
    public function findByDocumentoOrEmail(int $empresaId, ?string $documento, string $email): ?array
    {
        $sql = "SELECT * FROM clientes_web 
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
        $sql = "INSERT INTO clientes_web (
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
        $sql = "UPDATE clientes_web SET 
            nombre = :nombre, 
            apellido = :apellido, 
            telefono = :telefono, 
            direccion = :direccion, 
            localidad = :localidad, 
            provincia = :provincia, 
            codigo_postal = :codigo_postal,
            razon_social = :razon_social,
            updated_at = NOW()
            WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
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
    public function findAllPaginated(int $empresaId, int $page, int $limit, string $search, string $sort, string $dir): array
    {
        $offset = ($page - 1) * $limit;
        $orderDir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $allowedSorts = ['id', 'nombre', 'apellido', 'email', 'codigo_tango', 'id_gva14_tango', 'created_at'];
        $orderBy = in_array(strtolower($sort), $allowedSorts) ? $sort : 'created_at';

        $sql = "SELECT * FROM clientes_web WHERE empresa_id = :emp_id";
        $params = ['emp_id' => $empresaId];

        if (!empty($search)) {
            $sql .= " AND (nombre LIKE :s OR apellido LIKE :s OR email LIKE :s OR documento LIKE :s OR codigo_tango LIKE :s)";
            $params['s'] = "%$search%";
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
    public function countAll(int $empresaId, string $search): int
    {
        $sql = "SELECT COUNT(*) FROM clientes_web WHERE empresa_id = :emp_id";
        $params = ['emp_id' => $empresaId];

        if (!empty($search)) {
            $sql .= " AND (nombre LIKE :s OR apellido LIKE :s OR email LIKE :s OR documento LIKE :s OR codigo_tango LIKE :s)";
            $params['s'] = "%$search%";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca cliente por ID
     */
    public function findById(int $id, int $empresaId): ?array
    {
        $sql = "SELECT * FROM clientes_web WHERE id = :id AND empresa_id = :emp_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'emp_id' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Actualiza manualmente un cliente (ABM).
     */
    public function update(int $id, array $data): void
    {
        $sql = "UPDATE clientes_web SET 
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
            WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
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

    /**
     * Actualiza la información vinculada a Tango (Tras validación exitosa).
     */
    public function updateTangoData(int $id, array $tangoData): void
    {
        $sql = "UPDATE clientes_web SET 
            id_gva14_tango = :gva14,
            id_gva01_condicion_venta = :gva01,
            id_gva10_lista_precios = :gva10,
            id_gva23_vendedor = :gva23,
            id_gva24_transporte = :gva24,
            codigo_tango = :codigo_tango,
            updated_at = NOW()
            WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'gva14' => $tangoData['id_gva14_tango'] ?? null,
            'gva01' => $tangoData['id_gva01_condicion_venta'] ?? null,
            'gva10' => $tangoData['id_gva10_lista_precios'] ?? null,
            'gva23' => $tangoData['id_gva23_vendedor'] ?? null,
            'gva24' => $tangoData['id_gva24_transporte'] ?? null,
            'codigo_tango' => $tangoData['codigo_tango'] ?? null
        ]);
    }
}
