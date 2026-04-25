<?php

declare(strict_types=1);

namespace App\Core\Services;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

/**
 * NotificationService — único punto de emisión y lectura de notificaciones in-app.
 *
 * Patrón de uso (escritura):
 *
 *   (new NotificationService())->notify(
 *       empresaId:  $empresaId,
 *       usuarioId:  $userId,
 *       type:       'crm_horas.turno_olvidado',
 *       title:      'Tenés un turno abierto desde ayer',
 *       body:       'Iniciaste a las 09:00 y no cerraste. ¿Querés cerrarlo ahora?',
 *       link:       '/mi-empresa/crm/horas',
 *       data:       ['hora_id' => 42],
 *       dedupeKey:  'horas.olvido.user42.2026-04-23' // opcional, evita duplicados
 *   );
 *
 * Patrón de uso (lectura):
 *
 *   $unread = $service->countUnread($empresaId, $userId);
 *   $latest = $service->latest($empresaId, $userId, 5);
 *
 * Multi-tenant: TODA query filtra por empresa_id + usuario_id. No hay método
 *   "global" — siempre se trabaja por destinatario.
 *
 * Anti-duplicados: cuando se pasa $dedupeKey, el service chequea si existe una
 *   notificación con ese key (en la columna `data->>'$.dedupe_key'`) en las
 *   últimas 24hs y, si existe, no inserta. Útil para hooks que se disparan en
 *   cada request y no queremos spammear.
 */
class NotificationService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        // Red de seguridad idempotente — la migración es la fuente canónica.
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT NOT NULL,
                usuario_id INT NOT NULL,
                type VARCHAR(80) NOT NULL,
                title VARCHAR(255) NOT NULL,
                body TEXT NULL,
                link VARCHAR(500) NULL,
                data JSON NULL,
                read_at DATETIME NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL DEFAULT NULL,
                KEY idx_notif_inbox (empresa_id, usuario_id, deleted_at, read_at, created_at),
                KEY idx_notif_type (empresa_id, usuario_id, type, deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    /* ---------------------------------------------------------------------
     * Emisión
     * ------------------------------------------------------------------ */

    /**
     * Crea una notificación. Si $dedupeKey está seteado y existe otra notif
     * con el mismo key en las últimas 24hs para este usuario, no crea nada.
     *
     * @param array<string,mixed> $data Metadata libre (se serializa a JSON).
     * @return int ID de la notificación creada, o 0 si fue deduplicada.
     */
    public function notify(
        int $empresaId,
        int $usuarioId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
        array $data = [],
        ?string $dedupeKey = null
    ): int {
        if ($empresaId <= 0 || $usuarioId <= 0) {
            throw new InvalidArgumentException('empresa_id y usuario_id son obligatorios.');
        }
        $type = trim($type);
        $title = trim($title);
        if ($type === '' || $title === '') {
            throw new InvalidArgumentException('type y title son obligatorios.');
        }

        if ($dedupeKey !== null && $dedupeKey !== '') {
            $data['dedupe_key'] = $dedupeKey;
            if ($this->existsRecentDedupe($empresaId, $usuarioId, $dedupeKey)) {
                return 0;
            }
        }

        $stmt = $this->db->prepare('
            INSERT INTO notifications (empresa_id, usuario_id, type, title, body, link, data)
            VALUES (:empresa_id, :usuario_id, :type, :title, :body, :link, :data)
        ');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':usuario_id' => $usuarioId,
            ':type'       => $type,
            ':title'      => $title,
            ':body'       => $body,
            ':link'       => $link,
            ':data'       => $data === [] ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function existsRecentDedupe(int $empresaId, int $usuarioId, string $dedupeKey): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 FROM notifications
            WHERE empresa_id = :empresa_id
              AND usuario_id = :usuario_id
              AND deleted_at IS NULL
              AND created_at > (NOW() - INTERVAL 24 HOUR)
              AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.dedupe_key')) = :dk
            LIMIT 1
        ");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':usuario_id' => $usuarioId,
            ':dk'         => $dedupeKey,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    /* ---------------------------------------------------------------------
     * Lectura
     * ------------------------------------------------------------------ */

    public function countUnread(int $empresaId, int $usuarioId): int
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM notifications
            WHERE empresa_id = :e AND usuario_id = :u
              AND deleted_at IS NULL AND read_at IS NULL
        ');
        $stmt->execute([':e' => $empresaId, ':u' => $usuarioId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function latest(int $empresaId, int $usuarioId, int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->db->prepare("
            SELECT id, type, title, body, link, data, read_at, created_at
            FROM notifications
            WHERE empresa_id = :e AND usuario_id = :u AND deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT $limit
        ");
        $stmt->execute([':e' => $empresaId, ':u' => $usuarioId]);
        return array_map([$this, 'hydrateRow'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Listado paginado para la página "Ver todas".
     *
     * @return array{items:array<int,array<string,mixed>>, total:int, unread:int}
     */
    public function paginate(int $empresaId, int $usuarioId, int $page = 1, int $perPage = 25, string $filter = 'all'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = 'empresa_id = :e AND usuario_id = :u AND deleted_at IS NULL';
        if ($filter === 'unread') {
            $where .= ' AND read_at IS NULL';
        } elseif ($filter === 'read') {
            $where .= ' AND read_at IS NOT NULL';
        }

        $stmtItems = $this->db->prepare("
            SELECT id, type, title, body, link, data, read_at, created_at
            FROM notifications
            WHERE $where
            ORDER BY created_at DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmtItems->execute([':e' => $empresaId, ':u' => $usuarioId]);
        $items = array_map([$this, 'hydrateRow'], $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $stmtTotal = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE $where");
        $stmtTotal->execute([':e' => $empresaId, ':u' => $usuarioId]);
        $total = (int) $stmtTotal->fetchColumn();

        return [
            'items'  => $items,
            'total'  => $total,
            'unread' => $this->countUnread($empresaId, $usuarioId),
        ];
    }

    /* ---------------------------------------------------------------------
     * Mutación
     * ------------------------------------------------------------------ */

    public function markRead(int $empresaId, int $usuarioId, int $notifId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE notifications
               SET read_at = NOW()
             WHERE id = :id AND empresa_id = :e AND usuario_id = :u
               AND deleted_at IS NULL AND read_at IS NULL
        ');
        $stmt->execute([':id' => $notifId, ':e' => $empresaId, ':u' => $usuarioId]);
        return $stmt->rowCount() > 0;
    }

    public function markAllRead(int $empresaId, int $usuarioId): int
    {
        $stmt = $this->db->prepare('
            UPDATE notifications
               SET read_at = NOW()
             WHERE empresa_id = :e AND usuario_id = :u
               AND deleted_at IS NULL AND read_at IS NULL
        ');
        $stmt->execute([':e' => $empresaId, ':u' => $usuarioId]);
        return $stmt->rowCount();
    }

    public function softDelete(int $empresaId, int $usuarioId, int $notifId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE notifications
               SET deleted_at = NOW()
             WHERE id = :id AND empresa_id = :e AND usuario_id = :u
               AND deleted_at IS NULL
        ');
        $stmt->execute([':id' => $notifId, ':e' => $empresaId, ':u' => $usuarioId]);
        return $stmt->rowCount() > 0;
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------ */

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function hydrateRow(array $row): array
    {
        $row['data'] = $row['data'] ? json_decode((string) $row['data'], true) : [];
        $row['is_read'] = $row['read_at'] !== null;
        return $row;
    }
}
