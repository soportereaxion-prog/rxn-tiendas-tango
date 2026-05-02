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
    public function iniciar(int $empresaId, int $usuarioId, ?string $concepto, ?float $lat, ?float $lng, bool $consent, ?int $tratativaId = null, ?int $pdsId = null, ?int $clienteId = null, int $descuentoSegundos = 0, ?string $motivoDescuento = null): int
    {
        if ($this->repository->findOpenByUser($empresaId, $usuarioId) !== null) {
            throw new RuntimeException('Ya tenés un turno abierto. Cerralo antes de iniciar uno nuevo.');
        }

        $descuentoSegundos = max(0, $descuentoSegundos);
        $motivoDescuento = $motivoDescuento !== null ? trim($motivoDescuento) : null;
        if ($descuentoSegundos > 0 && ($motivoDescuento === null || $motivoDescuento === '')) {
            throw new InvalidArgumentException('Si hay descuento, el motivo es obligatorio.');
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
            'descuento_segundos' => $descuentoSegundos,
            'motivo_descuento'   => $motivoDescuento !== '' ? $motivoDescuento : null,
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
        ?int $clienteId = null,
        ?int $actorUserId = null,
        int $descuentoSegundos = 0,
        ?string $motivoDescuento = null,
        ?string $tmpUuidPwa = null
    ): int {
        // actorUserId opcional: cuando un admin carga el turno en nombre de
        // otro usuario, $usuarioId es el dueño del turno y $actorUserId es
        // quien lo está cargando. Si no se pasa, asumimos self-service (el
        // dueño se carga su propio turno).
        $actorUserId = $actorUserId ?? $usuarioId;

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

        // Validación descuento + motivo.
        $descuentoSegundos = max(0, $descuentoSegundos);
        $motivoDescuento = $motivoDescuento !== null ? trim($motivoDescuento) : null;
        if ($descuentoSegundos > 0 && ($motivoDescuento === null || $motivoDescuento === '')) {
            throw new InvalidArgumentException('Si hay descuento, el motivo es obligatorio.');
        }
        $duracionTotal = $end->getTimestamp() - $start->getTimestamp();
        if ($descuentoSegundos > $duracionTotal) {
            throw new InvalidArgumentException('El descuento no puede superar el tiempo total del turno.');
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
            'descuento_segundos' => $descuentoSegundos,
            'motivo_descuento'   => $motivoDescuento !== '' ? $motivoDescuento : null,
            'tratativa_id'  => $tratativaId ?: null,
            'pds_id'        => $pdsId ?: null,
            'cliente_id'    => $clienteId ?: null,
            'geo_diferido_lat' => $geoCargaLat,
            'geo_diferido_lng' => $geoCargaLng,
            'geo_consent_start' => $geoConsent ? 1 : 0,
            'inconsistencia_geo' => $inconsistencia,
            'created_by'    => $actorUserId,
            'tmp_uuid_pwa'  => $tmpUuidPwa,
        ]);

        $row = $this->repository->findById($newId, $empresaId);
        if ($row !== null) {
            $this->proyectar($row);
        }

        // Si quien lo cargó NO es el dueño del turno, queda audit + notificación
        // al dueño — mismo patrón que `anular()`.
        if ($actorUserId !== $usuarioId) {
            try {
                (new HoraAuditRepository())->record(
                    empresaId:    $empresaId,
                    horaId:       $newId,
                    ownerUserId:  $usuarioId,
                    accion:       'cargar_diferido',
                    before:       null,
                    after:        $row,
                    motivo:       'Carga diferida por administrador',
                    performedBy:  $actorUserId
                );
            } catch (\Throwable) {}

            try {
                (new NotificationService())->notify(
                    empresaId:  $empresaId,
                    usuarioId:  $usuarioId,
                    type:       'crm_horas.ajuste_admin',
                    title:      'Un admin cargó un turno a tu nombre',
                    body:       'Rango: ' . $startStr . ' → ' . $endStr,
                    link:       '/mi-empresa/crm/horas/listado',
                    data:       ['hora_id' => $newId, 'accion' => 'cargar_diferido', 'performed_by' => $actorUserId],
                    dedupeKey:  'horas.cargado.user' . $usuarioId . '.hora' . $newId
                );
            } catch (\Throwable) {}
        }

        return $newId;
    }

    /**
     * Edita un turno existente — exclusivo de admin (la autorización se hace
     * en el controller). Permite cambiar started_at, ended_at y concepto.
     *
     * Si el actor difiere del owner, se registra en audit con before/after y
     * se notifica al dueño. Si coinciden, igual se registra (a diferencia de
     * `anular`) porque cualquier mutación admin sobre un turno post-cierre es
     * sensible y querés trazabilidad — incluso si se editás el tuyo.
     */
    public function editar(
        int $empresaId,
        int $turnoId,
        string $startedAt,
        ?string $endedAt,
        ?string $concepto,
        string $motivo,
        int $actorUserId,
        ?int $descuentoSegundos = null,
        ?string $motivoDescuento = null
    ): void {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new InvalidArgumentException('Indicá el motivo de la edición.');
        }

        $before = $this->repository->findById($turnoId, $empresaId);
        if ($before === null) {
            throw new RuntimeException('El turno no existe o no pertenece a la empresa.');
        }

        $start = $this->parseDateTime($startedAt);
        $end = $endedAt !== null && $endedAt !== '' ? $this->parseDateTime($endedAt) : null;
        if ($start === null) {
            throw new InvalidArgumentException('Formato de inicio inválido.');
        }
        if ($endedAt !== null && $endedAt !== '' && $end === null) {
            throw new InvalidArgumentException('Formato de fin inválido.');
        }
        if ($end !== null && $end <= $start) {
            throw new InvalidArgumentException('El fin debe ser posterior al inicio.');
        }

        $startStr = $start->format('Y-m-d H:i:s');
        $endStr   = $end?->format('Y-m-d H:i:s');

        $ownerId = (int) $before['usuario_id'];
        if ($this->repository->hasOverlap($empresaId, $ownerId, $startStr, $endStr, $turnoId)) {
            throw new RuntimeException('El nuevo rango se solapa con otro turno del mismo operador.');
        }

        $updateData = [
            'started_at' => $startStr,
            'ended_at'   => $endStr,
            'concepto'   => $concepto !== null && trim($concepto) !== '' ? trim($concepto) : null,
        ];
        if ($descuentoSegundos !== null) {
            $descuentoSegundos = max(0, (int) $descuentoSegundos);
            $motivoDescuento = $motivoDescuento !== null ? trim($motivoDescuento) : null;
            if ($descuentoSegundos > 0 && ($motivoDescuento === null || $motivoDescuento === '')) {
                throw new InvalidArgumentException('Si hay descuento, el motivo es obligatorio.');
            }
            if ($end !== null && $descuentoSegundos > ($end->getTimestamp() - $start->getTimestamp())) {
                throw new InvalidArgumentException('El descuento no puede superar el tiempo total del turno.');
            }
            $updateData['descuento_segundos'] = $descuentoSegundos;
            $updateData['motivo_descuento']   = $motivoDescuento !== '' ? $motivoDescuento : null;
        }
        $this->repository->update($turnoId, $empresaId, $updateData);

        $after = $this->repository->findById($turnoId, $empresaId);

        // Re-proyectar a la agenda con el rango actualizado.
        if ($after !== null) {
            $this->proyectar($after);
        }

        // Audit + notificación SIEMPRE (a diferencia de anular, que solo logea
        // cuando actor != owner). Una edición admin es relevante incluso si
        // el admin se edita su propio turno — querés saber por qué cambió.
        try {
            (new HoraAuditRepository())->record(
                empresaId:    $empresaId,
                horaId:       $turnoId,
                ownerUserId:  $ownerId,
                accion:       'editar',
                before:       $before,
                after:        $after,
                motivo:       $motivo,
                performedBy:  $actorUserId
            );
        } catch (\Throwable) {}

        if ($actorUserId !== $ownerId) {
            try {
                (new NotificationService())->notify(
                    empresaId:  $empresaId,
                    usuarioId:  $ownerId,
                    type:       'crm_horas.ajuste_admin',
                    title:      'Un admin editó tu turno',
                    body:       'Motivo: ' . $motivo,
                    link:       '/mi-empresa/crm/horas/listado',
                    data:       ['hora_id' => $turnoId, 'accion' => 'editar', 'performed_by' => $actorUserId],
                    dedupeKey:  'horas.editado.user' . $ownerId . '.hora' . $turnoId . '.' . time()
                );
            } catch (\Throwable) {}
        }
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
