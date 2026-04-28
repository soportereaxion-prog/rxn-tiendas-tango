<?php

declare(strict_types=1);

namespace App\Core\Services;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * NotificationDispatcherService — barrido global de recordatorios vencidos.
 *
 * Lo invoca el endpoint `/api/internal/notifications/tick` desde n8n cada 1 min.
 * A diferencia del late firer dentro de NotificationController::feed() (que solo
 * dispara los recordatorios del usuario que abre la campanita), este servicio
 * itera TODAS las empresas y TODOS los usuarios — el usuario puede tener el
 * browser cerrado y la notificación igual se crea.
 *
 * Idempotencia:
 *   - Marca `recordatorio_disparado_at = NOW()` por cada nota disparada.
 *   - El dedupe del NotificationService (24h por dedupe_key) evita doble alta
 *     si por carrera el late firer y este tick procesan la misma nota.
 *
 * Sumar fuentes nuevas: cada "tipo de recordatorio" (notas, turnos, presupuestos
 * por vencer, etc.) tiene su propio método privado `dispatch{X}()`. tick() los
 * compone y devuelve el total. Mantener la separación para que el log diga qué
 * fuente generó qué.
 */
class NotificationDispatcherService
{
    private PDO $db;
    private NotificationService $notifier;

    public function __construct(?PDO $db = null, ?NotificationService $notifier = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->notifier = $notifier ?? new NotificationService($this->db);
    }

    /**
     * Procesa todos los recordatorios vencidos pendientes.
     *
     * @return array{processed:int, by_source:array<string,int>, errors:array<int,string>}
     */
    public function tick(): array
    {
        $bySource = [];
        $errors = [];

        try {
            $bySource['crm_notas'] = $this->dispatchCrmNotas($errors);
        } catch (Throwable $e) {
            $errors[] = 'crm_notas: ' . $e->getMessage();
            $bySource['crm_notas'] = 0;
        }

        return [
            'processed' => array_sum($bySource),
            'by_source' => $bySource,
            'errors'    => $errors,
        ];
    }

    /**
     * Recorre crm_notas con fecha_recordatorio vencida y sin disparar.
     * @param array<int,string> $errors (por referencia)
     */
    private function dispatchCrmNotas(array &$errors): int
    {
        $stmt = $this->db->prepare("
            SELECT id, empresa_id, created_by, titulo, contenido
            FROM crm_notas
            WHERE deleted_at IS NULL
              AND activo = 1
              AND created_by IS NOT NULL
              AND fecha_recordatorio IS NOT NULL
              AND recordatorio_disparado_at IS NULL
              AND fecha_recordatorio <= NOW()
            ORDER BY fecha_recordatorio ASC
            LIMIT 200
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($rows === []) {
            return 0;
        }

        $markStmt = $this->db->prepare(
            'UPDATE crm_notas SET recordatorio_disparado_at = NOW()
              WHERE id = :id AND empresa_id = :empresa_id'
        );

        $count = 0;
        foreach ($rows as $row) {
            $notaId    = (int) $row['id'];
            $empresaId = (int) $row['empresa_id'];
            $userId    = (int) $row['created_by'];
            $titulo    = (string) ($row['titulo'] ?? 'Nota');
            $body      = trim((string) ($row['contenido'] ?? ''));
            if (mb_strlen($body) > 200) {
                $body = mb_substr($body, 0, 197) . '...';
            }

            try {
                $this->notifier->notify(
                    empresaId: $empresaId,
                    usuarioId: $userId,
                    type: 'crm_notas.recordatorio',
                    title: '🔔 Recordatorio: ' . $titulo,
                    body: $body !== '' ? $body : null,
                    link: '/mi-empresa/crm/notas/' . $notaId . '/editar',
                    data: ['nota_id' => $notaId, 'source' => 'tick'],
                    dedupeKey: 'crm_notas.recordatorio.' . $notaId
                );
                $markStmt->execute([':id' => $notaId, ':empresa_id' => $empresaId]);
                $count++;
            } catch (Throwable $e) {
                $errors[] = sprintf('crm_notas#%d: %s', $notaId, $e->getMessage());
            }
        }

        return $count;
    }
}
