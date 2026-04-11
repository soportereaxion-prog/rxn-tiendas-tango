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

    public function onPresupuestoDeleted(int $presId, int $empresaId): void
    {
        try {
            $this->softDeleteAndMaybeRemote('presupuesto', $presId, $empresaId);
        } catch (\Throwable) {}
    }

    /**
     * Hook disparado por TratativaRepository cuando se crea o actualiza una tratativa
     * que tiene fecha_cierre_estimado definida.
     * Proyecta la tratativa como un evento all-day en el calendario.
     */
    public function onTratativaSaved(array $tratativa): void
    {
        try {
            $tratativaId = (int) ($tratativa['id'] ?? 0);
            $empresaId = (int) ($tratativa['empresa_id'] ?? 0);
            $fechaCierre = (string) ($tratativa['fecha_cierre_estimado'] ?? '');

            if ($tratativaId <= 0 || $empresaId <= 0 || $fechaCierre === '') {
                // Si no hay fecha de cierre, no hay evento proyectado. Si existia uno, lo borramos.
                if ($tratativaId > 0 && $empresaId > 0) {
                    $this->softDeleteAndMaybeRemote('tratativa', $tratativaId, $empresaId);
                }
                return;
            }

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
                'inicio' => $fechaCierre . ' 09:00:00',
                'fin' => $fechaCierre . ' 10:00:00',
                'all_day' => 1,
                'origen_tipo' => 'tratativa',
                'origen_id' => $tratativaId,
                'color' => AgendaRepository::defaultColorFor('tratativa'),
                'estado' => $estado,
            ]);
        } catch (\Throwable) {}
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
