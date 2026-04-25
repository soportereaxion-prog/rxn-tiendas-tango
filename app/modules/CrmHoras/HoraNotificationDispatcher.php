<?php

declare(strict_types=1);

namespace App\Modules\CrmHoras;

use App\Core\Database;
use App\Core\Services\NotificationService;
use App\Modules\Usuarios\UsuarioHorarioLaboralRepository;
use DateTimeImmutable;
use PDO;

/**
 * HoraNotificationDispatcher — emite notificaciones del módulo Horas.
 *
 * Charly definió que NO usemos cron por ahora. Los hooks viven en request-time:
 * cuando el operador entra al CRM dashboard, este dispatcher chequea condiciones
 * y emite las notificaciones que correspondan. NotificationService deduplica
 * dentro de las últimas 24hs por dedupe_key, así que aunque el operador entre
 * al dashboard 50 veces, la notif se crea una sola vez.
 *
 * Hooks implementados:
 *   - turno_olvidado: el operador tiene un turno abierto de un día anterior
 *     (no se cerró antes de medianoche → muy probablemente se olvidó).
 *   - olvidaste_cerrar: hay turno abierto del día actual y pasaron N minutos
 *     después del bloque_fin del horario laboral configurado.
 *   - no_iniciaste: el operador tiene `notif_no_iniciaste_activa=1` y la hora
 *     actual está dentro de un bloque del horario laboral, pero todavía no
 *     abrió ningún turno hoy.
 *
 * Idempotencia: dedupeKey por user + condición + fecha → una notif por día.
 */
class HoraNotificationDispatcher
{
    private NotificationService $notif;
    private HoraRepository $horaRepo;
    private UsuarioHorarioLaboralRepository $horarioRepo;
    private PDO $db;

    public function __construct(?NotificationService $notif = null, ?HoraRepository $horaRepo = null, ?UsuarioHorarioLaboralRepository $horarioRepo = null, ?PDO $db = null)
    {
        $this->notif = $notif ?? new NotificationService();
        $this->horaRepo = $horaRepo ?? new HoraRepository();
        $this->horarioRepo = $horarioRepo ?? new UsuarioHorarioLaboralRepository();
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Punto de entrada: corre todos los chequeos para un (empresa, usuario)
     * y emite las notifs que correspondan. Es idempotente vía dedupeKey.
     *
     * No bloquea — ningún chequeo individual rompe los demás.
     */
    public function checkAndNotify(int $empresaId, int $usuarioId): void
    {
        try { $this->checkTurnoOlvidado($empresaId, $usuarioId); } catch (\Throwable) {}
        try { $this->checkOlvidasteCerrar($empresaId, $usuarioId); } catch (\Throwable) {}
        try { $this->checkNoIniciaste($empresaId, $usuarioId); } catch (\Throwable) {}
    }

    /* ---------------------------------------------------------------------
     * Hooks
     * ------------------------------------------------------------------ */

    private function checkTurnoOlvidado(int $empresaId, int $usuarioId): void
    {
        $abierto = $this->horaRepo->findOpenByUser($empresaId, $usuarioId);
        if ($abierto === null) {
            return;
        }

        try {
            $start = new DateTimeImmutable((string) $abierto['started_at']);
        } catch (\Throwable) {
            return;
        }

        $today = new DateTimeImmutable('today');
        if ($start >= $today) {
            // El turno arrancó hoy — no es "olvidado", todavía está dentro del día.
            return;
        }

        $dedupe = sprintf('crm_horas.olvidado.user%d.%s', $usuarioId, $today->format('Y-m-d'));
        $this->notif->notify(
            empresaId: $empresaId,
            usuarioId: $usuarioId,
            type: 'crm_horas.turno_olvidado',
            title: 'Tenés un turno abierto desde un día anterior',
            body: 'Iniciaste el ' . $start->format('d/m/Y H:i') . ' y todavía no lo cerraste. Entrá al turnero para cerrarlo o anularlo.',
            link: '/mi-empresa/crm/horas',
            data: ['hora_id' => (int) $abierto['id'], 'started_at' => (string) $abierto['started_at']],
            dedupeKey: $dedupe
        );
    }

    private function checkOlvidasteCerrar(int $empresaId, int $usuarioId): void
    {
        $abierto = $this->horaRepo->findOpenByUser($empresaId, $usuarioId);
        if ($abierto === null) {
            return;
        }

        try {
            $start = new DateTimeImmutable((string) $abierto['started_at']);
        } catch (\Throwable) {
            return;
        }

        $today = new DateTimeImmutable('today');
        if ($start < $today) {
            // Ya disparó turno_olvidado — no necesitamos sumar otra notif.
            return;
        }

        // Buscar el bloque del día actual cuyo bloque_inicio sea <= started_at.
        $diaSemana = (int) $today->format('N'); // 1=Lun, 7=Dom
        $bloque = $this->horarioRepo->findCurrentDayBlock($usuarioId, $diaSemana, $start->format('H:i:s'));
        if ($bloque === null) {
            return;
        }

        $tolerancia = $this->fetchToleranciaUsuario($usuarioId);
        $finBloque = new DateTimeImmutable($today->format('Y-m-d') . ' ' . $bloque['bloque_fin']);
        $umbral = $finBloque->modify('+' . $tolerancia . ' minutes');
        $now = new DateTimeImmutable();

        if ($now < $umbral) {
            return;
        }

        $dedupe = sprintf('crm_horas.olvidaste_cerrar.user%d.%s.%s', $usuarioId, $today->format('Y-m-d'), $bloque['bloque_fin']);
        $this->notif->notify(
            empresaId: $empresaId,
            usuarioId: $usuarioId,
            type: 'crm_horas.olvidaste_cerrar',
            title: 'Te olvidaste de cerrar el turno',
            body: 'El bloque de hoy terminó a las ' . substr($bloque['bloque_fin'], 0, 5) . ' y pasaron más de ' . $tolerancia . ' min. Entrá al turnero para cerrarlo.',
            link: '/mi-empresa/crm/horas',
            data: ['hora_id' => (int) $abierto['id']],
            dedupeKey: $dedupe
        );
    }

    private function checkNoIniciaste(int $empresaId, int $usuarioId): void
    {
        // Solo si el usuario activó la preferencia.
        $u = $this->fetchUserNotifFlags($usuarioId);
        if (!$u || empty($u['notif_no_iniciaste_activa'])) {
            return;
        }

        $now = new DateTimeImmutable();
        $today = new DateTimeImmutable('today');
        $diaSemana = (int) $now->format('N');

        // Si ya tiene un turno (abierto o cerrado) hoy, no avisamos.
        $delDia = $this->horaRepo->findTodayByUser($empresaId, $usuarioId, $today->format('Y-m-d'));
        foreach ($delDia as $h) {
            if (($h['estado'] ?? '') !== 'anulado') {
                return;
            }
        }

        $bloque = $this->horarioRepo->findCurrentDayBlock($usuarioId, $diaSemana, $now->format('H:i:s'));
        if ($bloque === null) {
            return;
        }

        // Tolerancia: 10 min después de bloque_inicio sin turno abierto.
        $finiBloque = new DateTimeImmutable($today->format('Y-m-d') . ' ' . $bloque['bloque_inicio']);
        if ($now < $finiBloque->modify('+10 minutes')) {
            return;
        }
        // Y aún estamos dentro del bloque (no avisar si ya pasó toda la jornada).
        $finBloque = new DateTimeImmutable($today->format('Y-m-d') . ' ' . $bloque['bloque_fin']);
        if ($now >= $finBloque) {
            return;
        }

        $dedupe = sprintf('crm_horas.no_iniciaste.user%d.%s.%s', $usuarioId, $today->format('Y-m-d'), $bloque['bloque_inicio']);
        $this->notif->notify(
            empresaId: $empresaId,
            usuarioId: $usuarioId,
            type: 'crm_horas.no_iniciaste',
            title: 'Todavía no abriste turno',
            body: 'Tu horario laboral arrancó a las ' . substr($bloque['bloque_inicio'], 0, 5) . ' y todavía no abriste turno. Recordá hacerlo desde el turnero.',
            link: '/mi-empresa/crm/horas',
            data: ['bloque_inicio' => $bloque['bloque_inicio']],
            dedupeKey: $dedupe
        );
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------ */

    private function fetchToleranciaUsuario(int $usuarioId): int
    {
        try {
            $stmt = $this->db->prepare('SELECT minutos_tolerancia_olvido FROM usuarios WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $usuarioId]);
            $val = $stmt->fetchColumn();
            $val = (int) $val;
            return $val > 0 ? $val : 30;
        } catch (\Throwable) {
            return 30;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchUserNotifFlags(int $usuarioId): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT notif_no_iniciaste_activa, minutos_tolerancia_olvido FROM usuarios WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $usuarioId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
