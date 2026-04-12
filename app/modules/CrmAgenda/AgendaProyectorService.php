<?php
declare(strict_types=1);

namespace App\Modules\CrmAgenda;

use App\Core\Database;
use PDO;

/**
 * Servicio que recibe "eventos de dominio" desde los modulos (PDS, Presupuestos, Tratativas)
 * y los proyecta como eventos en la agenda (tabla crm_agenda_eventos).
 *
 * Cada modulo llama al hook correspondiente desde su repository.save() — es un hook explicito,
 * no un event bus. Ver MODULE_CONTEXT.md de CrmAgenda para el diseno.
 *
 * La proyeccion es idempotente:
 *   - Si ya existe un evento con (origen_tipo, origen_id) matcheante, se actualiza.
 *   - Si no existe, se crea.
 *   - Si el origen se elimina (soft-delete), el evento tambien.
 *
 * Toda excepcion adentro del proyector se traga silenciosamente para no romper el modulo origen.
 * Los errores se pueden recuperar posteriormente via un rescan manual.
 */
class AgendaProyectorService
{
    private AgendaRepository $repository;
    private ?GoogleCalendarSyncService $syncService;

    public function __construct(?AgendaRepository $repository = null, ?GoogleCalendarSyncService $syncService = null)
    {
        $this->repository = $repository ?? new AgendaRepository();
        // El sync a Google se inyecta de forma lazy. Si falla, no afecta la proyeccion local.
        $this->syncService = $syncService;
    }

    private function getSyncService(): GoogleCalendarSyncService
    {
        if ($this->syncService === null) {
            $this->syncService = new GoogleCalendarSyncService($this->repository);
        }
        return $this->syncService;
    }

    /**
     * Hook disparado por PedidoServicioRepository cuando se crea o actualiza un PDS.
     * La ventana temporal es [fecha_inicio, fecha_finalizado] (si no hay fecha_finalizado,
     * se usa fecha_inicio + 1h como fallback para que sea visible en el calendario).
     */
    public function onPdsSaved(array $pds): void
    {
        try {
            $pdsId = (int) ($pds['id'] ?? 0);
            $empresaId = (int) ($pds['empresa_id'] ?? 0);
            if ($pdsId <= 0 || $empresaId <= 0 || empty($pds['fecha_inicio'])) {
                return;
            }

            $titulo = 'PDS #' . (int) ($pds['numero'] ?? 0) . ' — ' . (string) ($pds['cliente_nombre'] ?? 'Sin cliente');
            $descripcion = trim((string) ($pds['solicito'] ?? '') . "\n\n" . (string) ($pds['diagnostico'] ?? ''));
            $inicio = (string) $pds['fecha_inicio'];
            $fin = !empty($pds['fecha_finalizado']) ? (string) $pds['fecha_finalizado'] : (string) (new \DateTimeImmutable($inicio))->modify('+1 hour')->format('Y-m-d H:i:s');

            $this->upsertEvent([
                'empresa_id' => $empresaId,
                'usuario_id' => $pds['usuario_id'] ?? null,
                'usuario_nombre' => $pds['usuario_nombre'] ?? null,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'inicio' => $inicio,
                'fin' => $fin,
                'origen_tipo' => 'pds',
                'origen_id' => $pdsId,
                'color' => AgendaRepository::defaultColorFor('pds'),
                'estado' => empty($pds['fecha_finalizado']) ? 'programado' : 'completado',
            ]);
        } catch (\Throwable) {
            // La proyeccion nunca rompe el save del modulo origen
        }
    }

    public function onPdsDeleted(int $pdsId, int $empresaId): void
    {
        try {
            $this->softDeleteAndMaybeRemote('pds', $pdsId, $empresaId);
        } catch (\Throwable) {}
    }

    /**
     * Hook disparado por PresupuestoRepository cuando se crea o actualiza un presupuesto.
     * Usa la fecha del presupuesto como punto de inicio, +30 min como fin (evento short-lived
     * tipo "revision comercial").
     */
    public function onPresupuestoSaved(array $presupuesto): void
    {
        try {
            $presId = (int) ($presupuesto['id'] ?? 0);
            $empresaId = (int) ($presupuesto['empresa_id'] ?? 0);
            if ($presId <= 0 || $empresaId <= 0 || empty($presupuesto['fecha'])) {
                return;
            }

            $titulo = 'Presupuesto #' . (int) ($presupuesto['numero'] ?? 0) . ' — ' . (string) ($presupuesto['cliente_nombre_snapshot'] ?? 'Sin cliente');
            $descripcion = 'Total: $' . number_format((float) ($presupuesto['total'] ?? 0), 2, ',', '.') . ' — Estado: ' . (string) ($presupuesto['estado'] ?? 'borrador');
            $inicio = (string) $presupuesto['fecha'];
            $fin = (new \DateTimeImmutable($inicio))->modify('+30 minutes')->format('Y-m-d H:i:s');

            $this->upsertEvent([
                'empresa_id' => $empresaId,
                'usuario_id' => $presupuesto['usuario_id'] ?? null,
                'usuario_nombre' => $presupuesto['usuario_nombre'] ?? null,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'inicio' => $inicio,
                'fin' => $fin,
                'origen_tipo' => 'presupuesto',
                'origen_id' => $presId,
                'color' => AgendaRepository::defaultColorFor('presupuesto'),
                'estado' => ($presupuesto['estado'] ?? '') === 'anulado' ? 'cancelado' : 'programado',
            ]);
        } catch (\Throwable) {}
    }

    /**
     * Hook para llamadas de la central telefonica.
     * Proyecta llamadas con duracion > 0 (las que fueron atendidas/completadas).
     */
    public function onLlamadaSaved(array $llamada): void
    {
        try {
            $llamadaId = (int) ($llamada['id'] ?? 0);
            $empresaId = (int) ($llamada['empresa_id'] ?? 0);
            if ($llamadaId <= 0 || $empresaId <= 0 || empty($llamada['fecha'])) {
                return;
            }

            $duracion = (int) ($llamada['duracion'] ?? 0);
            $origen = trim((string) ($llamada['numero_origen'] ?? $llamada['origen'] ?? ''));
            $destino = trim((string) ($llamada['destino'] ?? ''));
            $clienteNombre = trim((string) ($llamada['cliente_nombre'] ?? ''));

            $titulo = 'Llamada' . ($origen !== '' ? ' de ' . $origen : '') . ($clienteNombre !== '' ? ' — ' . $clienteNombre : '');
            $descripcion = 'Destino: ' . ($destino !== '' ? $destino : '-');
            if ($duracion > 0) {
                $h = intdiv($duracion, 3600);
                $m = intdiv($duracion % 3600, 60);
                $s = $duracion % 60;
                $descripcion .= "\nDuración: " . sprintf('%02d:%02d:%02d', $h, $m, $s);
            }

            $inicio = (string) $llamada['fecha'];
            $fin = (new \DateTimeImmutable($inicio))->modify('+' . max(60, $duracion) . ' seconds')->format('Y-m-d H:i:s');

            $this->upsertEvent([
                'empresa_id' => $empresaId,
                'usuario_id' => $llamada['usuario_id'] ?? null,
                'usuario_nombre' => $llamada['usuario_nombre'] ?? ($llamada['atendio'] ?? null),
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'inicio' => $inicio,
                'fin' => $fin,
                'origen_tipo' => 'llamada',
                'origen_id' => $llamadaId,
                'color' => AgendaRepository::defaultColorFor('llamada'),
                'estado' => 'completado',
            ]);
        } catch (\Throwable) {}
    }

    public function onLlamadaDeleted(int $llamadaId, int $empresaId): void
    {
        try {
            $this->softDeleteAndMaybeRemote('llamada', $llamadaId, $empresaId);
        } catch (\Throwable) {}
    }

    public function onPresupuestoDeleted(int $presId, int $empresaId): void
    {
        try {
            $this->softDeleteAndMaybeRemote('presupuesto', $presId, $empresaId);
        } catch (\Throwable) {}
    }

    /**
     * Hook disparado por TratativaRepository cuando se crea o actualiza una tratativa.
     * Proyecta la tratativa como un evento all-day en el calendario.
     *
     * Fallback de fechas: fecha_cierre_estimado → fecha_apertura → created_at → hoy.
     * De esta forma TODA tratativa tiene representación en el calendario,
     * independientemente de qué campos haya completado el operador.
     */
    public function onTratativaSaved(array $tratativa): void
    {
        try {
            $tratativaId = (int) ($tratativa['id'] ?? 0);
            $empresaId = (int) ($tratativa['empresa_id'] ?? 0);

            if ($tratativaId <= 0 || $empresaId <= 0) {
                return;
            }

            $fecha = $this->firstValidDate([
                $tratativa['fecha_cierre_estimado'] ?? null,
                $tratativa['fecha_apertura'] ?? null,
                $tratativa['created_at'] ?? null,
            ]) ?? (new \DateTimeImmutable())->format('Y-m-d');

            $titulo = 'Tratativa #' . (int) ($tratativa['numero'] ?? 0) . ' — ' . (string) ($tratativa['titulo'] ?? '');
            $descripcion = (string) ($tratativa['descripcion'] ?? '') . "\n\nCliente: " . (string) ($tratativa['cliente_nombre'] ?? 'Sin cliente') . "\nProbabilidad: " . (int) ($tratativa['probabilidad'] ?? 0) . '%';

            $estado = 'programado';
            if (($tratativa['estado'] ?? '') === 'ganada' || ($tratativa['estado'] ?? '') === 'perdida') {
                $estado = 'completado';
            } elseif (($tratativa['estado'] ?? '') === 'pausada') {
                $estado = 'cancelado';
            }

            $this->upsertEvent([
                'empresa_id' => $empresaId,
                'usuario_id' => $tratativa['usuario_id'] ?? null,
                'usuario_nombre' => $tratativa['usuario_nombre'] ?? null,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'inicio' => $fecha . ' 09:00:00',
                'fin' => $fecha . ' 10:00:00',
                'all_day' => 1,
                'origen_tipo' => 'tratativa',
                'origen_id' => $tratativaId,
                'color' => AgendaRepository::defaultColorFor('tratativa'),
                'estado' => $estado,
            ]);
        } catch (\Throwable) {}
    }

    /**
     * Devuelve la primera fecha válida (formato Y-m-d) de una lista de candidatos.
     * Ignora nulls, strings vacíos, '0000-00-00' y fechas inválidas.
     */
    private function firstValidDate(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value === '' || $value === '0000-00-00' || str_starts_with($value, '0000-00-00')) {
                continue;
            }
            try {
                return (new \DateTimeImmutable($value))->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }
        return null;
    }

    public function onTratativaDeleted(int $tratativaId, int $empresaId): void
    {
        try {
            $this->softDeleteAndMaybeRemote('tratativa', $tratativaId, $empresaId);
        } catch (\Throwable) {}
    }

    private function upsertEvent(array $data): void
    {
        $empresaId = (int) $data['empresa_id'];
        $origenTipo = (string) $data['origen_tipo'];
        $origenId = (int) $data['origen_id'];

        $existing = $this->repository->findByOrigen($empresaId, $origenTipo, $origenId);

        if ($existing !== null) {
            $data['google_event_id'] = $existing['google_event_id'] ?? null;
            $data['google_calendar_id'] = $existing['google_calendar_id'] ?? null;
            $data['synced_at'] = $existing['synced_at'] ?? null;
            $this->repository->update((int) $existing['id'], $empresaId, $data);
            $data['id'] = (int) $existing['id'];
        } else {
            $newId = $this->repository->create($data);
            $data['id'] = $newId;
            $data['google_event_id'] = null;
            $data['google_calendar_id'] = null;
        }

        // Push al Google Calendar del usuario (si hay auth activo).
        // El sync service se ocupa de decidir si hay auth y, si no, no hace nada silenciosamente.
        $this->getSyncService()->push($data);
    }

    private function softDeleteAndMaybeRemote(string $origenTipo, int $origenId, int $empresaId): void
    {
        $event = $this->repository->findByOrigen($empresaId, $origenTipo, $origenId);
        if ($event === null) {
            return;
        }

        // Intentamos borrar el remoto antes del soft-delete local — aunque falle, procedemos.
        if (!empty($event['google_event_id'])) {
            $this->getSyncService()->deleteRemote($event);
        }

        $this->repository->softDeleteByOrigen($empresaId, $origenTipo, $origenId);
    }
}
