<?php

declare(strict_types=1);

namespace App\Modules\CrmHoras;

use App\Core\Services\NotificationService;
use App\Modules\CrmAgenda\AgendaProyectorService;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * HoraService — orquesta las operaciones del turnero.
 *
 * Responsabilidades:
 *  - Validar reglas de negocio (no más de 1 turno abierto por usuario, no solapamientos).
 *  - Detectar inconsistencia de geo en cargas diferidas.
 *  - Disparar el hook a AgendaProyectorService cuando un turno se cierra.
 *
 * NO maneja: HTTP, sesiones, vistas. Solo lógica.
 */
class HoraService
{
    private HoraRepository $repository;
    private AgendaProyectorService $proyector;

    public function __construct(?HoraRepository $repository = null, ?AgendaProyectorService $proyector = null)
    {
        $this->repository = $repository ?? new HoraRepository();
        $this->proyector = $proyector ?? new AgendaProyectorService();
    }

    /**
     * Inicia un turno en vivo. Si ya hay uno abierto del usuario, falla.
     *
     * @return int ID del turno creado.
     * @throws RuntimeException
     */
    public function iniciar(int $empresaId, int $usuarioId, ?string $concepto, ?float $lat, ?float $lng, bool $consent, ?int $tratativaId = null, ?int $pdsId = null, ?int $clienteId = null): int
    {
        if ($this->repository->findOpenByUser($empresaId, $usuarioId) !== null) {
            throw new RuntimeException('Ya tenés un turno abierto. Cerralo antes de iniciar uno nuevo.');
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        return $this->repository->create([
            'empresa_id'    => $empresaId,
            'usuario_id'    => $usuarioId,
            'started_at'    => $now,
            'ended_at'      => null,
            'modo'          => 'en_vivo',
            'estado'        => 'abierto',
            'concepto'      => $concepto !== null && trim($concepto) !== '' ? trim($concepto) : null,
            'tratativa_id'  => $tratativaId ?: null,
            'pds_id'        => $pdsId ?: null,
            'cliente_id'    => $clienteId ?: null,
            'geo_start_lat' => $lat,
            'geo_start_lng' => $lng,
            'geo_consent_start' => $consent ? 1 : 0,
            'created_by'    => $usuarioId,
        ]);
    }

    /**
     * Cierra el turno abierto del usuario. Dispara hook a la agenda.
     *
     * @return array<string,mixed> Datos del turno cerrado.
     * @throws RuntimeException
     */
    public function cerrar(int $empresaId, int $usuarioId, ?float $lat, ?float $lng, bool $consent): array
    {
        $open = $this->repository->findOpenByUser($empresaId, $usuarioId);
        if ($open === null) {
            throw new RuntimeException('No hay turno abierto para cerrar.');
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->repository->close((int) $open['id'], $empresaId, $now, $lat, $lng, $consent);

        // Re-leer el row actualizado para proyectar a la agenda con datos finales.
        $closed = $this->repository->findById((int) $open['id'], $empresaId);
        if ($closed !== null) {
            $this->proyectar($closed);
        }
        return $closed ?? [];
    }

    /**
     * Carga un turno diferido (post-facto). Valida solapamientos y detecta
     * inconsistencia de geo (si la geo del momento de carga está muy lejos
     * de algún punto de referencia futuro — por ahora solo guardamos la geo
     * de carga y dejamos la flag para revisión manual del admin).
     *
     * @throws InvalidArgumentException si los datos son inválidos.
     * @throws RuntimeException si hay solapamiento.
     */
    public function cargarDiferido(
        int $empresaId,
        int $usuarioId,
        string $startedAt,
        string $endedAt,
        ?string $concepto,
        ?float $geoCargaLat,
        ?float $geoCargaLng,
        bool $geoConsent,
        ?int $tratativaId = null,
        ?int $pdsId = null,
        ?int $clienteId = null
    ): int {
        $start = $this->parseDateTime($startedAt);
        $end = $this->parseDateTime($endedAt);
        if ($start === null || $end === null) {
            throw new InvalidArgumentException('Formato de fecha inválido.');
        }
        if ($end <= $start) {
            throw new InvalidArgumentException('El fin debe ser posterior al inicio.');
        }

        $startStr = $start->format('Y-m-d H:i:s');
        $endStr   = $end->format('Y-m-d H:i:s');

        if ($this->repository->hasOverlap($empresaId, $usuarioId, $startStr, $endStr)) {
            throw new RuntimeException('El rango horario se solapa con otro turno tuyo.');
        }

        // Inconsistencia de geo: si pasaron más de 24hs entre el started_at del
        // turno y el momento de carga, marcamos para revisión (la geo del momento
        // de carga ya no es relevante respecto al lugar de trabajo).
        $inconsistencia = 0;
        $now = new DateTimeImmutable();
        $diffHs = ($now->getTimestamp() - $start->getTimestamp()) / 3600;
        if ($diffHs > 24 && $geoCargaLat !== null && $geoCargaLng !== null) {
            $inconsistencia = 1;
        }

        $newId = $this->repository->create([
            'empresa_id'    => $empresaId,
            'usuario_id'    => $usuarioId,
            'started_at'    => $startStr,
            'ended_at'      => $endStr,
            'modo'          => 'diferido',
            'estado'        => 'cerrado',
            'concepto'      => $concepto !== null && trim($concepto) !== '' ? trim($concepto) : null,
            'tratativa_id'  => $tratativaId ?: null,
            'pds_id'        => $pdsId ?: null,
            'cliente_id'    => $clienteId ?: null,
            'geo_diferido_lat' => $geoCargaLat,
            'geo_diferido_lng' => $geoCargaLng,
            'geo_consent_start' => $geoConsent ? 1 : 0,
            'inconsistencia_geo' => $inconsistencia,
            'created_by'    => $usuarioId,
        ]);

        $row = $this->repository->findById($newId, $empresaId);
        if ($row !== null) {
            $this->proyectar($row);
        }
        return $newId;
    }

    /**
     * Anula un turno (soft-delete lógico vía estado='anulado' con motivo).
     *
     * Si quien anula NO es el dueño del turno, se registra en audit log y se
     * notifica al dueño via NotificationService.
     */
    public function anular(int $empresaId, int $turnoId, string $motivo, ?int $performedBy = null): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new InvalidArgumentException('El motivo de anulación es obligatorio.');
        }

        // Snapshot antes de mutar — para audit log.
        $before = $this->repository->findById($turnoId, $empresaId);
        $ownerId = $before ? (int) $before['usuario_id'] : 0;

        $this->repository->annul($turnoId, $empresaId, $motivo);

        // El proyector borra el evento de la agenda.
        $this->proyector->onHoraDeleted($turnoId, $empresaId);

        // Audit + notificación al dueño SOLO si quien anula es distinto al dueño.
        if ($performedBy !== null && $ownerId > 0 && $performedBy !== $ownerId) {
            try {
                (new HoraAuditRepository())->record(
                    empresaId:    $empresaId,
                    horaId:       $turnoId,
                    ownerUserId:  $ownerId,
                    accion:       'anular',
                    before:       $before,
                    after:        ['motivo_anulacion' => $motivo],
                    motivo:       $motivo,
                    performedBy:  $performedBy
                );
            } catch (\Throwable) {}

            try {
                (new NotificationService())->notify(
                    empresaId:  $empresaId,
                    usuarioId:  $ownerId,
                    type:       'crm_horas.ajuste_admin',
                    title:      'Tu turno fue anulado por un admin',
                    body:       'Motivo: ' . $motivo,
                    link:       '/mi-empresa/crm/horas/listado',
                    data:       ['hora_id' => $turnoId, 'accion' => 'anular', 'performed_by' => $performedBy],
                    dedupeKey:  'horas.ajuste.user' . $ownerId . '.hora' . $turnoId
                );
            } catch (\Throwable) {}
        }
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------ */

    private function parseDateTime(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        // Aceptar "Y-m-d H:i:s", "Y-m-d H:i", "Y-m-d\TH:i" (input nativo).
        $value = str_replace('T', ' ', $value);
        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function proyectar(array $hora): void
    {
        try {
            $this->proyector->onHoraSaved($hora);
        } catch (\Throwable) {
            // Best-effort. Fallar la proyección no debe romper el cierre del turno.
        }
    }
}
