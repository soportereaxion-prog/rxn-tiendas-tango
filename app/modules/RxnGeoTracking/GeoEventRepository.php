<?php

declare(strict_types=1);

namespace App\Modules\RxnGeoTracking;

use App\Core\Database;
use PDO;

/**
 * Persistencia de eventos de geo-tracking.
 *
 * Todas las operaciones de escritura están tragadas con try/catch silencioso desde
 * el GeoTrackingService (fire-and-forget). Este repositorio NO intenta recuperarse
 * de errores — deja que PDO lance y el llamador decide.
 */
class GeoEventRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Crea un evento nuevo con datos server-side (IP + ubicación por IP).
     * Devuelve el ID del evento creado para que el frontend pueda luego
     * complementarlo con lat/lng más preciso via /geo-tracking/report.
     */
    public function create(array $payload): int
    {
        $stmt = $this->db->prepare('INSERT INTO rxn_geo_eventos (
                empresa_id,
                user_id,
                event_type,
                entidad_tipo,
                entidad_id,
                ip_address,
                lat,
                lng,
                accuracy_meters,
                accuracy_source,
                resolved_city,
                resolved_region,
                resolved_country,
                user_agent,
                consent_version,
                created_at
            ) VALUES (
                :empresa_id,
                :user_id,
                :event_type,
                :entidad_tipo,
                :entidad_id,
                :ip_address,
                :lat,
                :lng,
                :accuracy_meters,
                :accuracy_source,
                :resolved_city,
                :resolved_region,
                :resolved_country,
                :user_agent,
                :consent_version,
                NOW()
            )');

        $stmt->execute([
            ':empresa_id' => (int) $payload['empresa_id'],
            ':user_id' => (int) $payload['user_id'],
            ':event_type' => (string) $payload['event_type'],
            ':entidad_tipo' => $payload['entidad_tipo'] ?? null,
            ':entidad_id' => !empty($payload['entidad_id']) ? (int) $payload['entidad_id'] : null,
            ':ip_address' => $payload['ip_address'] ?? null,
            ':lat' => isset($payload['lat']) ? (float) $payload['lat'] : null,
            ':lng' => isset($payload['lng']) ? (float) $payload['lng'] : null,
            ':accuracy_meters' => isset($payload['accuracy_meters']) ? (int) $payload['accuracy_meters'] : null,
            ':accuracy_source' => (string) ($payload['accuracy_source'] ?? 'ip'),
            ':resolved_city' => $payload['resolved_city'] ?? null,
            ':resolved_region' => $payload['resolved_region'] ?? null,
            ':resolved_country' => $payload['resolved_country'] ?? null,
            ':user_agent' => $payload['user_agent'] ?? null,
            ':consent_version' => $payload['consent_version'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza un evento con lat/lng/accuracy/source obtenidos del browser.
     * Valida propiedad: solo el user_id dueño del evento puede modificarlo.
     * Devuelve true si se actualizó una fila (es decir: el evento existe y
     * pertenece al user de la sesión).
     */
    public function updatePositionFromBrowser(int $eventoId, int $userId, ?float $lat, ?float $lng, ?int $accuracyMeters, string $source): bool
    {
        $stmt = $this->db->prepare('UPDATE rxn_geo_eventos
            SET lat = :lat,
                lng = :lng,
                accuracy_meters = :accuracy_meters,
                accuracy_source = :accuracy_source
            WHERE id = :id
              AND user_id = :user_id');

        $stmt->execute([
            ':id' => $eventoId,
            ':user_id' => $userId,
            ':lat' => $lat,
            ':lng' => $lng,
            ':accuracy_meters' => $accuracyMeters,
            ':accuracy_source' => $source,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Borra eventos más viejos que N días para una empresa.
     * Usado por el job de purga (fase 6, pendiente).
     * Devuelve cuántas filas borró.
     */
    public function purgeOlderThan(int $empresaId, int $retentionDays): int
    {
        $stmt = $this->db->prepare('DELETE FROM rxn_geo_eventos
            WHERE empresa_id = :empresa_id
              AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)');
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $retentionDays, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    // ===== Queries del dashboard admin (fase 4) =====

    /**
     * Cuenta el total de eventos que matchean los filtros.
     * Usado para paginación del dashboard.
     *
     * $filters: [
     *   'date_from' => 'Y-m-d' | null,
     *   'date_to' => 'Y-m-d' | null,
     *   'user_id' => int | null,
     *   'event_type' => string | null,
     *   'entidad_tipo' => string | null,
     * ]
     */
    public function countAll(int $empresaId, array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) FROM rxn_geo_eventos e WHERE e.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];
        $this->applyFilters($sql, $params, $filters);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Trae eventos paginados con JOIN al usuario para mostrar el nombre.
     * Ordenado por created_at DESC.
     */
    public function findPaginated(int $empresaId, array $filters, int $page, int $limit): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $limit = max(1, min(500, $limit));

        $sql = 'SELECT e.*,
                u.nombre AS user_nombre,
                u.email AS user_email
            FROM rxn_geo_eventos e
            LEFT JOIN usuarios u ON u.id = e.user_id
            WHERE e.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];
        $this->applyFilters($sql, $params, $filters);

        $sql .= ' ORDER BY e.created_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Devuelve puntos para el mapa (solo los que tienen lat/lng).
     * Limitado a 500 para no sobrecargar Google Maps. El dashboard
     * muestra un banner si hay más de 500 matcheando los filtros.
     */
    public function findForMap(int $empresaId, array $filters, int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));

        $sql = 'SELECT e.id,
                e.event_type,
                e.entidad_tipo,
                e.entidad_id,
                e.lat,
                e.lng,
                e.accuracy_source,
                e.accuracy_meters,
                e.resolved_city,
                e.resolved_country,
                e.ip_address,
                e.created_at,
                u.nombre AS user_nombre
            FROM rxn_geo_eventos e
            LEFT JOIN usuarios u ON u.id = e.user_id
            WHERE e.empresa_id = :empresa_id
              AND e.lat IS NOT NULL
              AND e.lng IS NOT NULL';
        $params = [':empresa_id' => $empresaId];
        $this->applyFilters($sql, $params, $filters);

        $sql .= ' ORDER BY e.created_at DESC LIMIT ' . (int) $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Trae eventos para export CSV. Límite duro de 10k filas por request
     * para evitar OOM. Si hay más, el admin tiene que acotar el rango.
     */
    public function findForExport(int $empresaId, array $filters, int $limit = 10000): array
    {
        $limit = max(1, min(10000, $limit));

        $sql = 'SELECT e.id,
                e.event_type,
                e.entidad_tipo,
                e.entidad_id,
                e.ip_address,
                e.lat,
                e.lng,
                e.accuracy_meters,
                e.accuracy_source,
                e.resolved_city,
                e.resolved_region,
                e.resolved_country,
                e.user_agent,
                e.consent_version,
                e.created_at,
                u.nombre AS user_nombre,
                u.email AS user_email
            FROM rxn_geo_eventos e
            LEFT JOIN usuarios u ON u.id = e.user_id
            WHERE e.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];
        $this->applyFilters($sql, $params, $filters);

        $sql .= ' ORDER BY e.created_at DESC LIMIT ' . (int) $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Detalle de un evento — usado por el popup del mapa al hacer click.
     * Filtra por empresa_id para mantener aislamiento multi-tenant.
     */
    public function findById(int $id, int $empresaId): ?array
    {
        $stmt = $this->db->prepare('SELECT e.*, u.nombre AS user_nombre, u.email AS user_email
            FROM rxn_geo_eventos e
            LEFT JOIN usuarios u ON u.id = e.user_id
            WHERE e.id = :id AND e.empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Helper para listar usuarios que tuvieron al menos un evento en el rango.
     * Usado para poblar el selector de usuario en los filtros.
     */
    public function findDistinctUsersInRange(int $empresaId, array $filters): array
    {
        // Reutilizamos applyFilters pero sin el filtro de user_id (que es lo que queremos descubrir).
        $filtersSinUser = $filters;
        unset($filtersSinUser['user_id']);

        $sql = 'SELECT DISTINCT e.user_id, u.nombre AS user_nombre
            FROM rxn_geo_eventos e
            LEFT JOIN usuarios u ON u.id = e.user_id
            WHERE e.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];
        $this->applyFilters($sql, $params, $filtersSinUser);

        $sql .= ' ORDER BY u.nombre ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Aplica filtros comunes al SQL (paginado, map, export, count).
     * Valida whitelist de event_type y entidad_tipo para evitar SQL injection.
     */
    private function applyFilters(string &$sql, array &$params, array $filters): void
    {
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if ($dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $sql .= ' AND e.created_at >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $sql .= ' AND e.created_at <= :date_to';
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }

        if (!empty($filters['user_id'])) {
            $sql .= ' AND e.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['event_type']) && in_array($filters['event_type'], GeoTrackingService::VALID_EVENT_TYPES, true)) {
            $sql .= ' AND e.event_type = :event_type';
            $params[':event_type'] = $filters['event_type'];
        }

        if (!empty($filters['entidad_tipo'])) {
            $allowedEntityTypes = ['presupuesto', 'tratativa', 'pds'];
            if (in_array($filters['entidad_tipo'], $allowedEntityTypes, true)) {
                $sql .= ' AND e.entidad_tipo = :entidad_tipo';
                $params[':entidad_tipo'] = $filters['entidad_tipo'];
            }
        }
    }
}
