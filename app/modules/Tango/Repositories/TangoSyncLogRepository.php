<?php
declare(strict_types=1);
namespace App\Modules\Tango\Repositories;

use App\Core\Database;
use PDO;

class TangoSyncLogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Da inicio a un registro de Sincronización, marcándolo como IN_PROGRESS.
     */
    public function startLog(int $empresaId, string $tipoSync): int
    {
        $sql = "INSERT INTO tango_sync_logs (empresa_id, tipo_sync, fecha_inicio, estado) 
                VALUES (:empresa_id, :tipo, NOW(), 'IN_PROGRESS')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':tipo' => $tipoSync
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Cierra el registro indicando el recuento total de mapeos y persistencias.
     */
    public function endLog(int $logId, array $stats, string $estado, ?string $mensajeError = null): void
    {
        $sql = "UPDATE tango_sync_logs SET 
                fecha_fin = NOW(),
                total_recibidos = :recibidos,
                total_insertados = :insertados,
                total_actualizados = :actualizados,
                total_omitidos = :omitidos,
                estado = :estado,
                mensaje_error = :error
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':recibidos' => $stats['recibidos'] ?? 0,
            ':insertados' => $stats['insertados'] ?? 0,
            ':actualizados' => $stats['actualizados'] ?? 0,
            ':omitidos' => $stats['omitidos'] ?? 0,
            ':estado' => $estado,
            ':error' => $mensajeError,
            ':id' => $logId
        ]);
    }
}
