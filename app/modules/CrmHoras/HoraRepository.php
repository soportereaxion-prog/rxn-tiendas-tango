<?php

declare(strict_types=1);

namespace App\Modules\CrmHoras;

use App\Core\Database;
use PDO;

/**
 * HoraRepository — CRUD de turnos del módulo CrmHoras.
 *
 * Multi-tenant: TODA query exige empresa_id. Operador-scoped: las queries de
 *   "mis turnos" filtran por usuario_id; las queries admin no.
 */
class HoraRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Devuelve el turno abierto del usuario, si existe.
     * Solo puede haber uno (decisión 6: no se permite tener varios abiertos).
     *
     * @return array<string,mixed>|null
     */
    public function findOpenByUser(int $empresaId, int $usuarioId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM crm_horas
            WHERE empresa_id = :e AND usuario_id = :u
              AND estado = 'abierto' AND deleted_at IS NULL
            ORDER BY started_at DESC
            LIMIT 1
        ");
        $stmt->execute([':e' => $empresaId, ':u' => $usuarioId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Turnos del día actual del usuario (cerrados + abiertos), ordenados
     * cronológicamente. Sirve para la lista del turnero.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findTodayByUser(int $empresaId, int $usuarioId, string $todayDate): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM crm_horas
            WHERE empresa_id = :e AND usuario_id = :u
              AND deleted_at IS NULL
              AND DATE(started_at) = :d
            ORDER BY started_at ASC
        ");
        $stmt->execute([':e' => $empresaId, ':u' => $usuarioId, ':d' => $todayDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, int $empresaId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM crm_horas
            WHERE id = :id AND empresa_id = :e AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':e' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Listado paginado para vista admin/supervisor (todos los operadores).
     *
     * @return array{items:array<int,array<string,mixed>>, total:int}
     */
    public function paginate(int $empresaId, ?int $usuarioFilter = null, ?string $desde = null, ?string $hasta = null, int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = 'h.empresa_id = :e AND h.deleted_at IS NULL';
        $params = [':e' => $empresaId];
        if ($usuarioFilter && $usuarioFilter > 0) {
            $where .= ' AND h.usuario_id = :u';
            $params[':u'] = $usuarioFilter;
        }
        if ($desde) {
            $where .= ' AND h.started_at >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }
        if ($hasta) {
            $where .= ' AND h.started_at <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $stmtItems = $this->db->prepare("
            SELECT h.*, u.nombre AS usuario_nombre
            FROM crm_horas h
            LEFT JOIN usuarios u ON u.id = h.usuario_id
            WHERE $where
            ORDER BY h.started_at DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmtItems->execute($params);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmtTotal = $this->db->prepare("SELECT COUNT(*) FROM crm_horas h WHERE $where");
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Lista turnos vinculados a una tratativa específica.
     * Sirve para la sección "Horas trabajadas" en el detalle de la tratativa.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findByTratativa(int $empresaId, int $tratativaId): array
    {
        $stmt = $this->db->prepare("
            SELECT h.*, u.nombre AS usuario_nombre
            FROM crm_horas h
            LEFT JOIN usuarios u ON u.id = h.usuario_id
            WHERE h.empresa_id = :e AND h.tratativa_id = :t AND h.deleted_at IS NULL
            ORDER BY h.started_at DESC
        ");
        $stmt->execute([':e' => $empresaId, ':t' => $tratativaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Devuelve los turnos cerrados del usuario que NO están vinculados a ninguna tratativa.
     * Sirve para alimentar el modal "Vincular hora existente" en el detalle de tratativa.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findSueltosByUser(int $empresaId, int $usuarioId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->db->prepare("
            SELECT id, started_at, ended_at, concepto, modo, estado
            FROM crm_horas
            WHERE empresa_id = :e AND usuario_id = :u
              AND deleted_at IS NULL AND estado = 'cerrado'
              AND tratativa_id IS NULL
            ORDER BY started_at DESC
            LIMIT $limit
        ");
        $stmt->execute([':e' => $empresaId, ':u' => $usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Vincula un turno existente a una tratativa. Solo aplica si el turno
     * pertenece a la misma empresa. No valida ownership de tratativa — se
     * asume que el caller ya lo hizo.
     */
    public function setTratativa(int $horaId, int $empresaId, ?int $tratativaId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE crm_horas SET tratativa_id = :t
            WHERE id = :id AND empresa_id = :e AND deleted_at IS NULL
        ");
        $stmt->execute([':t' => $tratativaId, ':id' => $horaId, ':e' => $empresaId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Detecta solapamientos de horario para un usuario.
     * Útil para la validación de carga diferida (decisión 15).
     */
    public function hasOverlap(int $empresaId, int $usuarioId, string $startedAt, ?string $endedAt, ?int $excludeId = null): bool
    {
        $endParam = $endedAt ?? $startedAt;
        $sql = "
            SELECT 1 FROM crm_horas
            WHERE empresa_id = :e AND usuario_id = :u
              AND estado != 'anulado' AND deleted_at IS NULL
              AND started_at < :end
              AND COALESCE(ended_at, NOW()) > :start
        ";
        $params = [':e' => $empresaId, ':u' => $usuarioId, ':start' => $startedAt, ':end' => $endParam];
        if ($excludeId) {
            $sql .= ' AND id != :ex';
            $params[':ex'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO crm_horas
                (empresa_id, usuario_id, started_at, ended_at, modo, estado, concepto,
                 descuento_segundos, motivo_descuento,
                 tratativa_id, pds_id, cliente_id,
                 geo_start_lat, geo_start_lng, geo_consent_start,
                 geo_end_lat, geo_end_lng, geo_consent_end,
                 geo_diferido_lat, geo_diferido_lng, inconsistencia_geo,
                 created_by, tmp_uuid_pwa)
            VALUES
                (:empresa_id, :usuario_id, :started_at, :ended_at, :modo, :estado, :concepto,
                 :descuento_segundos, :motivo_descuento,
                 :tratativa_id, :pds_id, :cliente_id,
                 :gsl, :gsg, :gcs,
                 :gel, :geg, :gce,
                 :gdl, :gdg, :ig,
                 :created_by, :tmp_uuid_pwa)
        ');
        $stmt->execute([
            ':empresa_id' => $data['empresa_id'],
            ':usuario_id' => $data['usuario_id'],
            ':started_at' => $data['started_at'],
            ':ended_at'   => $data['ended_at'] ?? null,
            ':modo'       => $data['modo'] ?? 'en_vivo',
            ':estado'     => $data['estado'] ?? 'abierto',
            ':concepto'   => $data['concepto'] ?? null,
            ':descuento_segundos' => (int) ($data['descuento_segundos'] ?? 0),
            ':motivo_descuento'   => $data['motivo_descuento'] ?? null,
            ':tratativa_id' => $data['tratativa_id'] ?? null,
            ':pds_id'     => $data['pds_id'] ?? null,
            ':cliente_id' => $data['cliente_id'] ?? null,
            ':gsl' => $data['geo_start_lat'] ?? null,
            ':gsg' => $data['geo_start_lng'] ?? null,
            ':gcs' => $data['geo_consent_start'] ?? 0,
            ':gel' => $data['geo_end_lat'] ?? null,
            ':geg' => $data['geo_end_lng'] ?? null,
            ':gce' => $data['geo_consent_end'] ?? 0,
            ':gdl' => $data['geo_diferido_lat'] ?? null,
            ':gdg' => $data['geo_diferido_lng'] ?? null,
            ':ig'  => $data['inconsistencia_geo'] ?? 0,
            ':created_by' => $data['created_by'] ?? null,
            ':tmp_uuid_pwa' => isset($data['tmp_uuid_pwa']) && $data['tmp_uuid_pwa'] !== '' ? (string) $data['tmp_uuid_pwa'] : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Busca un turno por su tmp_uuid_pwa (idempotencia del sync mobile).
     */
    public function findByTmpUuidPwa(string $tmpUuid, int $empresaId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_horas
            WHERE tmp_uuid_pwa = :tmp_uuid AND empresa_id = :e LIMIT 1');
        $stmt->execute([':tmp_uuid' => $tmpUuid, ':e' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function close(int $id, int $empresaId, string $endedAt, ?float $lat = null, ?float $lng = null, bool $consent = false): bool
    {
        $stmt = $this->db->prepare("
            UPDATE crm_horas SET
                ended_at = :ended_at,
                geo_end_lat = :lat,
                geo_end_lng = :lng,
                geo_consent_end = :consent,
                estado = 'cerrado'
            WHERE id = :id AND empresa_id = :e AND estado = 'abierto' AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':ended_at' => $endedAt,
            ':lat' => $lat,
            ':lng' => $lng,
            ':consent' => $consent ? 1 : 0,
            ':id' => $id,
            ':e' => $empresaId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function update(int $id, int $empresaId, array $data): bool
    {
        // Update parcial — solo campos que la UI permita editar.
        $set = [];
        $params = [':id' => $id, ':e' => $empresaId];
        foreach (['started_at', 'ended_at', 'concepto', 'descuento_segundos', 'motivo_descuento', 'tratativa_id', 'pds_id', 'cliente_id'] as $f) {
            if (array_key_exists($f, $data)) {
                $set[] = "$f = :$f";
                $params[":$f"] = $data[$f];
            }
        }
        if (!$set) return true;

        $sql = "UPDATE crm_horas SET " . implode(', ', $set) . " WHERE id = :id AND empresa_id = :e AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function annul(int $id, int $empresaId, string $motivo): bool
    {
        $stmt = $this->db->prepare("
            UPDATE crm_horas SET
                estado = 'anulado',
                motivo_anulacion = :m
            WHERE id = :id AND empresa_id = :e AND deleted_at IS NULL
        ");
        $stmt->execute([':m' => $motivo, ':id' => $id, ':e' => $empresaId]);
        return $stmt->rowCount() > 0;
    }
}
