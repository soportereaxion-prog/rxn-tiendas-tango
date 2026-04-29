<?php
declare(strict_types=1);

namespace App\Modules\CrmAgenda;

use App\Core\Database;
use PDO;

class AgendaRepository
{
    public const ORIGENES = ['manual', 'pds', 'presupuesto', 'tratativa', 'llamada', 'tratativa_accion', 'hora', 'nota', 'presupuesto_proximo_contacto', 'presupuesto_vigencia'];
    public const ESTADOS = ['programado', 'en_curso', 'completado', 'cancelado'];

    private const DEFAULT_COLORS = [
        'manual' => '#6c757d',
        'pds' => '#0d6efd',
        'presupuesto' => '#198754',
        'tratativa' => '#ffc107',
        'tratativa_accion' => '#fd7e14',
        'llamada' => '#6610f2',
        'hora' => '#20c997',  // teal — turnos trabajados (módulo CrmHoras)
        'nota' => '#d63384',  // pink — notas con recordatorio (módulo CrmNotas)
        'presupuesto_proximo_contacto' => '#0dcaf0',  // cyan/info — próximo contacto agendado del presupuesto
        'presupuesto_vigencia' => '#dc3545',          // red/danger — deadline de vigencia del presupuesto
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public static function defaultColorFor(string $origenTipo): string
    {
        return self::DEFAULT_COLORS[$origenTipo] ?? '#6c757d';
    }

    /**
     * Retorna eventos en un rango de fechas para el calendario (FullCalendar events feed).
     */
    /**
     * Retorna eventos en un rango de fechas para el calendario (FullCalendar events feed).
     * Hace JOIN con usuarios para traer el color_calendario del operador asignado.
     */
    /**
     * @param int|int[]|null $usuarioIdFilter — un solo ID, array de IDs, o null (sin filtro)
     */
    public function findInRange(int $empresaId, string $startIso, string $endIso, int|array|null $usuarioIdFilter = null, ?array $origenesFilter = null): array
    {
        $sql = 'SELECT e.*, u.color_calendario AS color_usuario
            FROM crm_agenda_eventos e
            LEFT JOIN usuarios u ON u.id = e.usuario_id
            WHERE e.empresa_id = :empresa_id
              AND e.deleted_at IS NULL
              AND e.inicio < :end_iso
              AND e.fin >= :start_iso';
        $params = [
            ':empresa_id' => $empresaId,
            ':start_iso' => $startIso,
            ':end_iso' => $endIso,
        ];

        if (is_int($usuarioIdFilter) && $usuarioIdFilter > 0) {
            $sql .= ' AND e.usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioIdFilter;
        } elseif (is_array($usuarioIdFilter) && $usuarioIdFilter !== []) {
            $uPlaceholders = [];
            foreach ($usuarioIdFilter as $i => $uid) {
                $key = ':uid_' . $i;
                $uPlaceholders[] = $key;
                $params[$key] = (int) $uid;
            }
            $sql .= ' AND e.usuario_id IN (' . implode(',', $uPlaceholders) . ')';
        }

        if ($origenesFilter !== null && $origenesFilter !== []) {
            $placeholders = [];
            foreach ($origenesFilter as $i => $origen) {
                $key = ':origen_' . $i;
                $placeholders[] = $key;
                $params[$key] = $origen;
            }
            $sql .= ' AND e.origen_tipo IN (' . implode(',', $placeholders) . ')';
        }

        $sql .= ' ORDER BY e.inicio ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, int $empresaId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_agenda_eventos WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Busca un evento existente por su origen (polimorfico).
     * Usado por el AgendaProyectorService para decidir entre insertar o actualizar.
     */
    public function findByOrigen(int $empresaId, string $origenTipo, int $origenId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_agenda_eventos
            WHERE empresa_id = :empresa_id
              AND origen_tipo = :origen_tipo
              AND origen_id = :origen_id
              AND deleted_at IS NULL
            LIMIT 1');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':origen_tipo' => $origenTipo,
            ':origen_id' => $origenId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO crm_agenda_eventos (
                empresa_id,
                usuario_id,
                usuario_nombre,
                titulo,
                descripcion,
                ubicacion,
                inicio,
                fin,
                all_day,
                color,
                estado,
                origen_tipo,
                origen_id,
                google_event_id,
                google_calendar_id,
                synced_at,
                created_at,
                updated_at
            ) VALUES (
                :empresa_id,
                :usuario_id,
                :usuario_nombre,
                :titulo,
                :descripcion,
                :ubicacion,
                :inicio,
                :fin,
                :all_day,
                :color,
                :estado,
                :origen_tipo,
                :origen_id,
                :google_event_id,
                :google_calendar_id,
                :synced_at,
                NOW(),
                NOW()
            )');
        $stmt->execute($this->buildPayload($data));

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $empresaId, array $data): bool
    {
        $data['empresa_id'] = $empresaId;

        $stmt = $this->db->prepare('UPDATE crm_agenda_eventos SET
                usuario_id = :usuario_id,
                usuario_nombre = :usuario_nombre,
                titulo = :titulo,
                descripcion = :descripcion,
                ubicacion = :ubicacion,
                inicio = :inicio,
                fin = :fin,
                all_day = :all_day,
                color = :color,
                estado = :estado,
                origen_tipo = :origen_tipo,
                origen_id = :origen_id,
                google_event_id = :google_event_id,
                google_calendar_id = :google_calendar_id,
                synced_at = :synced_at,
                updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id');

        $payload = $this->buildPayload($data);
        $payload[':id'] = $id;

        return $stmt->execute($payload);
    }

    public function softDeleteById(int $id, int $empresaId): bool
    {
        $stmt = $this->db->prepare('UPDATE crm_agenda_eventos SET deleted_at = NOW() WHERE id = :id AND empresa_id = :empresa_id');
        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);
    }

    /**
     * Soft-delete de todos los eventos que apunten a un origen concreto.
     * Lo usa el AgendaProyectorService cuando un PDS/Presupuesto/Tratativa se elimina.
     */
    public function softDeleteByOrigen(int $empresaId, string $origenTipo, int $origenId): int
    {
        $stmt = $this->db->prepare('UPDATE crm_agenda_eventos
            SET deleted_at = NOW()
            WHERE empresa_id = :empresa_id
              AND origen_tipo = :origen_tipo
              AND origen_id = :origen_id
              AND deleted_at IS NULL');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':origen_tipo' => $origenTipo,
            ':origen_id' => $origenId,
        ]);

        return $stmt->rowCount();
    }

    public function markSynced(int $id, int $empresaId, string $googleEventId, string $googleCalendarId): bool
    {
        $stmt = $this->db->prepare('UPDATE crm_agenda_eventos
            SET google_event_id = :google_event_id,
                google_calendar_id = :google_calendar_id,
                synced_at = NOW(),
                sync_error = NULL,
                updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id');
        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':google_event_id' => $googleEventId,
            ':google_calendar_id' => $googleCalendarId,
        ]);
    }

    /**
     * Persiste el JSON de multi-sync tracking en la columna google_syncs.
     */
    public function updateGoogleSyncs(int $id, int $empresaId, array $syncs): bool
    {
        $stmt = $this->db->prepare('UPDATE crm_agenda_eventos
            SET google_syncs = :syncs,
                updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id');
        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':syncs' => json_encode($syncs, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function markSyncError(int $id, int $empresaId, string $errorMessage): bool
    {
        $stmt = $this->db->prepare('UPDATE crm_agenda_eventos
            SET sync_error = :sync_error,
                updated_at = NOW()
            WHERE id = :id AND empresa_id = :empresa_id');
        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':sync_error' => $errorMessage,
        ]);
    }

    public function findUnsyncedForUser(int $empresaId, int $usuarioId, int $limit = 100): array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_agenda_eventos
            WHERE empresa_id = :empresa_id
              AND usuario_id = :usuario_id
              AND google_event_id IS NULL
              AND deleted_at IS NULL
            ORDER BY inicio ASC
            LIMIT :limit');
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Retorna la lista de usuarios activos de la empresa con su color de calendario.
     * Usado para renderizar las pills de filtro por usuario en la vista de la Agenda.
     */
    public function findUsuariosConColor(int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT id, nombre, color_calendario
            FROM usuarios
            WHERE empresa_id = :empresa_id AND activo = 1 AND deleted_at IS NULL
            ORDER BY nombre ASC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function buildPayload(array $data): array
    {
        $origenTipo = (string) ($data['origen_tipo'] ?? 'manual');
        if (!in_array($origenTipo, self::ORIGENES, true)) {
            $origenTipo = 'manual';
        }

        $estado = (string) ($data['estado'] ?? 'programado');
        if (!in_array($estado, self::ESTADOS, true)) {
            $estado = 'programado';
        }

        return [
            ':empresa_id' => (int) $data['empresa_id'],
            ':usuario_id' => !empty($data['usuario_id']) ? (int) $data['usuario_id'] : null,
            ':usuario_nombre' => $data['usuario_nombre'] ?? null,
            ':titulo' => (string) $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':ubicacion' => $data['ubicacion'] ?? null,
            ':inicio' => $data['inicio'],
            ':fin' => $data['fin'],
            ':all_day' => !empty($data['all_day']) ? 1 : 0,
            ':color' => $data['color'] ?? self::defaultColorFor($origenTipo),
            ':estado' => $estado,
            ':origen_tipo' => $origenTipo,
            ':origen_id' => !empty($data['origen_id']) ? (int) $data['origen_id'] : null,
            ':google_event_id' => $data['google_event_id'] ?? null,
            ':google_calendar_id' => $data['google_calendar_id'] ?? null,
            ':synced_at' => $data['synced_at'] ?? null,
        ];
    }
}
