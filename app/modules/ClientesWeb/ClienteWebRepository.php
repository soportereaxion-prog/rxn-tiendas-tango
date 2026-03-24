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
}
