<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos;

use App\Core\Database;
use PDO;

/**
 * Acceso a las tablas `crm_mail_jobs` y `crm_mail_job_items`.
 *
 * Todo está scopeado por empresa_id. Los items tienen su propio empresa_id
 * (redundante pero útil para queries indexadas sin JOIN).
 */
class JobRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // ──────────────────────────────────────────────
    // LISTADO / LECTURA
    // ──────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllByEmpresa(int $empresaId, string $search = ''): array
    {
        $sql = "SELECT j.id, j.asunto, j.estado, j.cancel_flag,
                       j.total_destinatarios, j.total_enviados, j.total_fallidos, j.total_skipped,
                       j.created_at, j.started_at, j.finished_at,
                       j.report_id, j.template_id,
                       r.nombre AS report_nombre,
                       t.nombre AS template_nombre
                FROM crm_mail_jobs j
                LEFT JOIN crm_mail_reports r ON r.id = j.report_id
                LEFT JOIN crm_mail_templates t ON t.id = j.template_id
                WHERE j.empresa_id = :empresa_id";

        $params = [':empresa_id' => $empresaId];

        if ($search !== '') {
            $sql .= " AND (j.asunto LIKE :search OR r.nombre LIKE :search OR t.nombre LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY j.created_at DESC LIMIT 200';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByIdForEmpresa(int $jobId, int $empresaId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT j.*, r.nombre AS report_nombre, t.nombre AS template_nombre
             FROM crm_mail_jobs j
             LEFT JOIN crm_mail_reports r ON r.id = j.report_id
             LEFT JOIN crm_mail_templates t ON t.id = j.template_id
             WHERE j.id = :id AND j.empresa_id = :empresa_id
             LIMIT 1"
        );
        $stmt->execute([':id' => $jobId, ':empresa_id' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Datos livianos para el polling del monitor. Solo contadores + estado +
     * cancel_flag. No trae body_snapshot ni payload de items.
     */
    public function findLiveStatusForEmpresa(int $jobId, int $empresaId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, estado, cancel_flag,
                    total_destinatarios, total_enviados, total_fallidos, total_skipped,
                    started_at, finished_at, updated_at, mensaje_error
             FROM crm_mail_jobs
             WHERE id = :id AND empresa_id = :empresa_id
             LIMIT 1"
        );
        $stmt->execute([':id' => $jobId, ':empresa_id' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Items (primeros N) para mostrar en el monitor. Incluye conteos
     * agregados de aperturas y clicks (Fase 5).
     *
     * @return list<array<string, mixed>>
     */
    public function findItemsForJob(int $jobId, int $empresaId, int $limit = 100, ?string $estado = null): array
    {
        $sql = "SELECT i.id, i.recipient_email, i.recipient_name, i.estado,
                       i.sent_at, i.error_msg, i.created_at,
                       SUM(CASE WHEN e.tipo = 'open'  THEN 1 ELSE 0 END) AS opens,
                       SUM(CASE WHEN e.tipo = 'click' THEN 1 ELSE 0 END) AS clicks
                FROM crm_mail_job_items i
                LEFT JOIN crm_mail_tracking_events e ON e.job_item_id = i.id
                WHERE i.job_id = :job_id AND i.empresa_id = :empresa_id";
        $params = [':job_id' => $jobId, ':empresa_id' => $empresaId];

        if ($estado !== null && $estado !== '') {
            $sql .= " AND i.estado = :estado";
            $params[':estado'] = $estado;
        }

        $sql .= " GROUP BY i.id ORDER BY i.id ASC LIMIT " . max(1, min(1000, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Resumen agregado de tracking para la cabecera del monitor (Fase 5).
     *
     * @return array{opens: int, clicks: int, unique_openers: int, unique_clickers: int}
     */
    public function findTrackingSummaryForJob(int $jobId, int $empresaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                SUM(CASE WHEN e.tipo = 'open'  THEN 1 ELSE 0 END) AS opens,
                SUM(CASE WHEN e.tipo = 'click' THEN 1 ELSE 0 END) AS clicks,
                COUNT(DISTINCT CASE WHEN e.tipo = 'open'  THEN e.job_item_id END) AS unique_openers,
                COUNT(DISTINCT CASE WHEN e.tipo = 'click' THEN e.job_item_id END) AS unique_clickers
             FROM crm_mail_tracking_events e
             JOIN crm_mail_job_items i ON i.id = e.job_item_id
             WHERE i.job_id = :job_id AND e.empresa_id = :emp"
        );
        $stmt->execute([':job_id' => $jobId, ':emp' => $empresaId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'opens' => (int) ($r['opens'] ?? 0),
            'clicks' => (int) ($r['clicks'] ?? 0),
            'unique_openers' => (int) ($r['unique_openers'] ?? 0),
            'unique_clickers' => (int) ($r['unique_clickers'] ?? 0),
        ];
    }

    // ──────────────────────────────────────────────
    // ESCRITURA
    // ──────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    public function createJob(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO crm_mail_jobs
                (empresa_id, usuario_id, report_id, template_id, smtp_config_id,
                 asunto, body_snapshot, attachments_json,
                 estado, total_destinatarios)
             VALUES
                (:empresa_id, :usuario_id, :report_id, :template_id, :smtp_config_id,
                 :asunto, :body_snapshot, :attachments_json,
                 'queued', :total_destinatarios)"
        );
        $stmt->execute([
            ':empresa_id' => $data['empresa_id'],
            ':usuario_id' => $data['usuario_id'],
            ':report_id' => $data['report_id'] ?? null,
            ':template_id' => $data['template_id'] ?? null,
            ':smtp_config_id' => $data['smtp_config_id'],
            ':asunto' => $data['asunto'],
            ':body_snapshot' => $data['body_snapshot'],
            ':attachments_json' => $data['attachments_json'] ?? null,
            ':total_destinatarios' => (int) ($data['total_destinatarios'] ?? 0),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Inserta items en batch. Cada item tiene un tracking_token único.
     *
     * @param list<array<string, mixed>> $items
     */
    public function createJobItems(int $jobId, int $empresaId, array $items): int
    {
        if (empty($items)) return 0;

        $sql = "INSERT INTO crm_mail_job_items
                    (job_id, empresa_id, recipient_email, recipient_name, recipient_data_json, tracking_token)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $count = 0;

        foreach ($items as $item) {
            $stmt->execute([
                $jobId,
                $empresaId,
                $item['recipient_email'],
                $item['recipient_name'] ?? null,
                $item['recipient_data_json'] ?? null,
                $item['tracking_token'],
            ]);
            $count++;
        }

        return $count;
    }

    public function setCancelFlag(int $jobId, int $empresaId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE crm_mail_jobs
             SET cancel_flag = 1
             WHERE id = :id AND empresa_id = :empresa_id
               AND estado IN ('queued','running','paused')"
        );
        $stmt->execute([':id' => $jobId, ':empresa_id' => $empresaId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update de estado desde callback de n8n (o admin). Se valida empresa_id
     * explícitamente porque el webhook de callback puede ser llamado externamente.
     *
     * @param array<string, mixed> $data
     */
    public function updateJobStateFromCallback(int $jobId, int $empresaId, array $data): bool
    {
        $allowed = ['estado', 'total_enviados', 'total_fallidos', 'total_skipped',
                    'started_at', 'finished_at', 'mensaje_error'];

        $sets = [];
        $params = [':id' => $jobId, ':empresa_id' => $empresaId];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($sets)) return false;

        $sql = "UPDATE crm_mail_jobs SET " . implode(', ', $sets)
             . " WHERE id = :id AND empresa_id = :empresa_id";

        $stmt = $this->db->prepare($sql);
        return (bool) $stmt->execute($params);
    }

    /**
     * Update de item individual (callback de n8n por destinatario enviado/fallido).
     *
     * @param array<string, mixed> $data
     */
    public function updateJobItemState(string $trackingToken, int $empresaId, array $data): bool
    {
        $allowed = ['estado', 'sent_at', 'error_msg'];
        $sets = [];
        $params = [':token' => $trackingToken, ':empresa_id' => $empresaId];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (empty($sets)) return false;

        $sql = "UPDATE crm_mail_job_items SET " . implode(', ', $sets)
             . " WHERE tracking_token = :token AND empresa_id = :empresa_id";

        $stmt = $this->db->prepare($sql);
        return (bool) $stmt->execute($params);
    }

    // ──────────────────────────────────────────────
    // HELPERS DE LECTURA PARA EL CREATOR
    // ──────────────────────────────────────────────

    /**
     * Reporte + plantilla + smtp del usuario, en una sola llamada.
     * @return array{report: ?array, template: ?array, smtp: ?array}
     */
    public function loadJobContext(int $empresaId, int $usuarioId, int $reportId, int $templateId): array
    {
        $report = null;
        $template = null;
        $smtp = null;

        if ($reportId > 0) {
            $stmt = $this->db->prepare(
                "SELECT * FROM crm_mail_reports
                 WHERE id = :id AND empresa_id = :emp AND deleted_at IS NULL LIMIT 1"
            );
            $stmt->execute([':id' => $reportId, ':emp' => $empresaId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($templateId > 0) {
            $stmt = $this->db->prepare(
                "SELECT * FROM crm_mail_templates
                 WHERE id = :id AND empresa_id = :emp AND deleted_at IS NULL LIMIT 1"
            );
            $stmt->execute([':id' => $templateId, ':emp' => $empresaId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM crm_mail_smtp_configs
             WHERE empresa_id = :emp AND usuario_id = :uid AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([':emp' => $empresaId, ':uid' => $usuarioId]);
        $smtp = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return ['report' => $report, 'template' => $template, 'smtp' => $smtp];
    }

    /**
     * Lista plantillas + reportes disponibles para el creator.
     * @return array{reports: list<array>, templates: list<array>}
     */
    public function listChoices(int $empresaId): array
    {
        $reports = $this->db->prepare(
            "SELECT id, nombre, root_entity FROM crm_mail_reports
             WHERE empresa_id = :emp AND deleted_at IS NULL ORDER BY nombre ASC"
        );
        $reports->execute([':emp' => $empresaId]);

        $templates = $this->db->prepare(
            "SELECT id, nombre, asunto, report_id FROM crm_mail_templates
             WHERE empresa_id = :emp AND deleted_at IS NULL ORDER BY nombre ASC"
        );
        $templates->execute([':emp' => $empresaId]);

        return [
            'reports' => $reports->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'templates' => $templates->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }
}
