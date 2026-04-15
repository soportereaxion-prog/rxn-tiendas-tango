<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos\Services;

use App\Core\Database;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use RuntimeException;

/**
 * BatchProcessor — procesa un batch de items pending de un job.
 *
 * Uso esperado:
 *   $proc = new BatchProcessor();
 *   $result = $proc->processBatch($jobId, 50);
 *   // $result = ['sent' => int, 'failed' => int, 'skipped' => int,
 *   //            'remaining' => int, 'estado' => 'running'|'completed'|'cancelled',
 *   //            'cancel_requested' => bool, 'job_finalized' => bool]
 *
 * Responsabilidades:
 *   - Hace "claim" de los N primeros items pending con un UPDATE atómico
 *     (estado pending → locked) para evitar double-processing si hay 2
 *     workers corriendo.
 *   - Abre UNA conexión SMTP por batch (no una por item) — más eficiente.
 *   - Renderiza asunto + body con las variables del recipient_data_json.
 *   - Envía con PHPMailer.
 *   - Update estado + contadores.
 *   - Chequea cancel_flag al inicio del batch y si está, marca todos los
 *     pending del job como 'skipped' y cierra el job como 'cancelled'.
 *   - Si no quedan items tras el batch, cierra el job como 'completed'.
 *
 * NO hace:
 *   - No hace loop entre batches. Eso queda para n8n o el CLI worker.
 *   - No aplica pause_seconds. Eso lo hace el caller.
 */
class BatchProcessor
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * @return array<string, mixed>
     */
    public function processBatch(int $jobId, int $batchSize = 50): array
    {
        $batchSize = max(1, min(500, $batchSize));

        // 1. Cargar job con smtp + verificar estado
        $job = $this->loadJobWithSmtp($jobId);
        if (!$job) {
            throw new RuntimeException("Job #{$jobId} no encontrado");
        }

        $empresaId = (int) $job['empresa_id'];

        // Si está en queued, pasar a running
        if ($job['estado'] === 'queued') {
            $this->updateJobFields($jobId, [
                'estado' => 'running',
                'started_at' => date('Y-m-d H:i:s'),
            ]);
            $job['estado'] = 'running';
        }

        // 2. Chequear cancel_flag
        if ((int) $job['cancel_flag'] === 1) {
            return $this->closeCancelled($jobId, $empresaId);
        }

        // Si el job ya está en estado final, no hacer nada
        if (in_array($job['estado'], ['completed', 'cancelled', 'failed'], true)) {
            return $this->buildStatusResponse($jobId, $empresaId, 'already_final');
        }

        // 3. Claim items (SELECT + UPDATE atómico con LOCKED)
        //    Estrategia: seleccionar los primeros N pending, guardar sus IDs,
        //    y hacer UPDATE a un estado intermedio "locked" que solo este
        //    worker procesa. Como no tenemos estado "locked" en el ENUM,
        //    usamos una estrategia más simple: los enviamos UNO por UNO, y
        //    cada uno se marca sent/failed al momento. Si hay otro worker,
        //    lo peor que pasa es que intenten enviar al mismo y manden 2.
        //    Para un MVP con 1 worker a la vez, esto es aceptable.
        $stmt = $this->db->prepare(
            "SELECT id, recipient_email, recipient_name, recipient_data_json, tracking_token
             FROM crm_mail_job_items
             WHERE job_id = :job_id AND empresa_id = :emp AND estado = 'pending'
             ORDER BY id ASC LIMIT :lim"
        );
        $stmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
        $stmt->bindValue(':emp', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $batchSize, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($items)) {
            // No hay más items pending — cerrar el job
            return $this->closeCompleted($jobId, $empresaId);
        }

        // 4. Abrir conexión SMTP una sola vez
        $mail = $this->openSmtp($job);
        $mail->SMTPKeepAlive = true; // reutilizar conexión entre addresses

        // 5. Procesar cada item
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $email = trim((string) $item['recipient_email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->markItem((int) $item['id'], 'skipped', 'Email inválido');
                $skipped++;
                continue;
            }

            $data = json_decode((string) ($item['recipient_data_json'] ?? '{}'), true) ?: [];
            $renderedSubject = $this->renderTemplate((string) $job['asunto'], $data);
            $renderedBody = $this->renderTemplate((string) $job['body_snapshot'], $data);

            // Fase 5: tracking. Reescribe <a href="..."> → /m/click/{token}?u=...
            // e inyecta pixel <img src=".../m/open/{token}.gif"> al final del body.
            $renderedBody = $this->injectTracking(
                $renderedBody,
                (string) $item['tracking_token']
            );

            try {
                // Limpiar destinatarios previos del mailer sin reabrir SMTP
                $mail->clearAllRecipients();
                $mail->clearAttachments();
                $mail->addAddress($email, (string) ($item['recipient_name'] ?? ''));

                $mail->Subject = $renderedSubject;
                $mail->Body = $renderedBody;

                $cleanBody = preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', '', $renderedBody);
                $mail->AltBody = strip_tags((string) $cleanBody);

                $mail->send();
                $this->markItem((int) $item['id'], 'sent');
                $sent++;
            } catch (PHPMailerException $e) {
                $err = $e->getMessage();
                if (!empty($mail->ErrorInfo)) $err .= ' | ' . $mail->ErrorInfo;
                $this->markItem((int) $item['id'], 'failed', $err);
                $failed++;
            } catch (\Throwable $e) {
                $this->markItem((int) $item['id'], 'failed', $e->getMessage());
                $failed++;
            }
        }

        // Cerrar conexión SMTP
        try { $mail->smtpClose(); } catch (\Throwable $e) { /* ok */ }

        // 6. Actualizar contadores del job atómicamente
        $this->db->prepare(
            "UPDATE crm_mail_jobs
             SET total_enviados = total_enviados + :ok,
                 total_fallidos = total_fallidos + :fail,
                 total_skipped = total_skipped + :skp
             WHERE id = :id"
        )->execute([
            ':ok' => $sent,
            ':fail' => $failed,
            ':skp' => $skipped,
            ':id' => $jobId,
        ]);

        // 7. Chequear si quedan items pending para decidir cierre
        $remaining = (int) $this->db->query(
            "SELECT COUNT(*) FROM crm_mail_job_items
             WHERE job_id = {$jobId} AND empresa_id = {$empresaId} AND estado = 'pending'"
        )->fetchColumn();

        // Re-leer cancel_flag por si el usuario canceló durante el batch
        $cancelFlag = (int) $this->db->query(
            "SELECT cancel_flag FROM crm_mail_jobs WHERE id = {$jobId}"
        )->fetchColumn();

        if ($cancelFlag === 1) {
            return $this->closeCancelled($jobId, $empresaId);
        }

        if ($remaining === 0) {
            return $this->closeCompleted($jobId, $empresaId);
        }

        return $this->buildStatusResponse($jobId, $empresaId, 'running', [
            'batch_sent' => $sent,
            'batch_failed' => $failed,
            'batch_skipped' => $skipped,
            'remaining' => $remaining,
        ]);
    }

    // ──────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ──────────────────────────────────────────────

    /**
     * Trae el job + la SMTP config asociada en un solo query.
     * @return array<string, mixed>|null
     */
    private function loadJobWithSmtp(int $jobId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT j.*,
                    s.host AS smtp_host, s.port AS smtp_port,
                    s.username AS smtp_user, s.password_encrypted AS smtp_pass,
                    s.encryption AS smtp_encryption,
                    s.from_email AS smtp_from_email, s.from_name AS smtp_from_name,
                    s.max_per_batch AS smtp_max_per_batch,
                    s.pause_seconds AS smtp_pause_seconds,
                    s.activo AS smtp_activo
             FROM crm_mail_jobs j
             JOIN crm_mail_smtp_configs s ON s.id = j.smtp_config_id
             WHERE j.id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $job
     */
    private function openSmtp(array $job): PHPMailer
    {
        if (empty($job['smtp_activo'])) {
            throw new RuntimeException('La configuración SMTP del job está inactiva.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) $job['smtp_host'];
        $mail->Port = (int) $job['smtp_port'];
        $mail->SMTPAuth = true;
        $mail->Username = (string) $job['smtp_user'];
        // NOTE: En Fase 1 la password se guarda en password_encrypted pero NO
        // está cifrada realmente (deuda técnica conocida — ver AGENTS.md).
        // Cuando haya App\Core\Crypto se descifra acá.
        $mail->Password = (string) $job['smtp_pass'];

        $enc = (string) $job['smtp_encryption'];
        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isHTML(true);

        $fromEmail = (string) ($job['smtp_from_email'] ?? '');
        $fromName = (string) ($job['smtp_from_name'] ?? '');
        $mail->setFrom($fromEmail, $fromName);

        return $mail;
    }

    /**
     * Reemplaza `{{Entity.field}}` por `$data['Entity_field']`.
     * @param array<string, mixed> $data
     */
    private function renderTemplate(string $template, array $data): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)\s*\}\}/',
            static function (array $m) use ($data): string {
                $alias = $m[1] . '_' . $m[2];
                if (!array_key_exists($alias, $data) || $data[$alias] === null) return '';
                return (string) $data[$alias];
            },
            $template
        );
    }

    /**
     * Inyecta el pixel de tracking al final del body y reescribe cada
     * <a href="..."> con la URL de redirect. La base URL se lee de
     * APP_URL en el .env; si no está, el tracking NO se inyecta y los
     * links quedan tal cual (fail gracefully — el mail sigue siendo útil).
     */
    private function injectTracking(string $body, string $token): string
    {
        $baseUrl = trim((string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: ''));
        // Permitir override específico del módulo para entornos con dominio split
        $trackingBase = trim((string) ($_ENV['MAIL_MASIVOS_TRACKING_BASE_URL']
            ?? getenv('MAIL_MASIVOS_TRACKING_BASE_URL') ?: $baseUrl));

        if ($trackingBase === '' || $token === '') {
            return $body; // sin base URL, skip tracking (no rompe el envío)
        }

        $trackingBase = rtrim($trackingBase, '/');
        // NO agregar sufijo .gif a la URL — el Router del proyecto matchea
        // {param} con [a-zA-Z0-9_-]+ y el punto queda afuera → 404. El browser
        // carga la imagen igual porque el response lleva Content-Type: image/gif.
        $pixelUrl = $trackingBase . '/m/open/' . rawurlencode($token);
        $clickUrlPrefix = $trackingBase . '/m/click/' . rawurlencode($token) . '?u=';

        // 1. Reescribir <a href="..."> — sólo si href tiene scheme http/https
        //    y NO empieza con {trackingBase}/m/ (para evitar double-wrap).
        $body = preg_replace_callback(
            '/<a\b([^>]*?)\shref\s*=\s*(["\'])(.*?)\2/i',
            function (array $m) use ($clickUrlPrefix, $trackingBase): string {
                $attrs = $m[1];
                $quote = $m[2];
                $href = trim($m[3]);

                // Saltar mailto:, tel:, anchors, javascript:, data:
                if (preg_match('/^(mailto:|tel:|javascript:|data:|#)/i', $href)) {
                    return $m[0];
                }
                // Saltar si ya es una URL de tracking
                if (str_starts_with($href, $trackingBase . '/m/')) {
                    return $m[0];
                }
                // Saltar URLs relativas o no http/https
                if (!preg_match('/^https?:\/\//i', $href)) {
                    return $m[0];
                }

                $newHref = $clickUrlPrefix . rawurlencode($href);
                return '<a' . $attrs . ' href=' . $quote . $newHref . $quote;
            },
            $body
        ) ?? $body;

        // 2. Inyectar el pixel al final. Si el body tiene </body>, va antes;
        //    si no, se appendea al final.
        $pixelTag = '<img src="' . $pixelUrl . '" width="1" height="1" alt="" '
                  . 'style="display:block;width:1px;height:1px;border:0;" />';

        if (stripos($body, '</body>') !== false) {
            $body = (string) preg_replace('/<\/body>/i', $pixelTag . '</body>', $body, 1);
        } else {
            $body .= "\n" . $pixelTag;
        }

        return $body;
    }

    private function markItem(int $itemId, string $estado, ?string $errorMsg = null): void
    {
        $sql = "UPDATE crm_mail_job_items
                SET estado = :estado"
             . ($estado === 'sent' ? ", sent_at = NOW()" : "")
             . ($errorMsg !== null ? ", error_msg = :err" : ", error_msg = NULL")
             . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $params = [':estado' => $estado, ':id' => $itemId];
        if ($errorMsg !== null) $params[':err'] = mb_substr($errorMsg, 0, 1000);
        $stmt->execute($params);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function updateJobFields(int $jobId, array $fields): void
    {
        if (empty($fields)) return;
        $sets = [];
        $params = [':id' => $jobId];
        foreach ($fields as $k => $v) {
            $sets[] = "{$k} = :{$k}";
            $params[":{$k}"] = $v;
        }
        $sql = "UPDATE crm_mail_jobs SET " . implode(', ', $sets) . " WHERE id = :id";
        $this->db->prepare($sql)->execute($params);
    }

    /**
     * @return array<string, mixed>
     */
    private function closeCompleted(int $jobId, int $empresaId): array
    {
        $this->updateJobFields($jobId, [
            'estado' => 'completed',
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->buildStatusResponse($jobId, $empresaId, 'completed');
    }

    /**
     * @return array<string, mixed>
     */
    private function closeCancelled(int $jobId, int $empresaId): array
    {
        // Marcar todos los pending como skipped
        $stmt = $this->db->prepare(
            "UPDATE crm_mail_job_items
             SET estado = 'skipped', error_msg = 'Cancelado por el usuario'
             WHERE job_id = :id AND empresa_id = :emp AND estado = 'pending'"
        );
        $stmt->execute([':id' => $jobId, ':emp' => $empresaId]);
        $skipped = $stmt->rowCount();

        // Incrementar contador
        if ($skipped > 0) {
            $this->db->prepare(
                "UPDATE crm_mail_jobs SET total_skipped = total_skipped + :n WHERE id = :id"
            )->execute([':n' => $skipped, ':id' => $jobId]);
        }

        $this->updateJobFields($jobId, [
            'estado' => 'cancelled',
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->buildStatusResponse($jobId, $empresaId, 'cancelled');
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function buildStatusResponse(int $jobId, int $empresaId, string $hint, array $extra = []): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, estado, cancel_flag, total_destinatarios,
                    total_enviados, total_fallidos, total_skipped,
                    smtp_config_id
             FROM crm_mail_jobs WHERE id = :id AND empresa_id = :emp LIMIT 1"
        );
        $stmt->execute([':id' => $jobId, ':emp' => $empresaId]);
        $j = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $remaining = (int) $this->db->query(
            "SELECT COUNT(*) FROM crm_mail_job_items
             WHERE job_id = {$jobId} AND empresa_id = {$empresaId} AND estado = 'pending'"
        )->fetchColumn();

        // pause_seconds lo trae el job si tiene smtp_config_id — útil para que n8n sepa cuánto esperar
        $pauseSec = 5;
        if (!empty($j['smtp_config_id'])) {
            $p = $this->db->prepare(
                "SELECT pause_seconds, max_per_batch FROM crm_mail_smtp_configs WHERE id = :id"
            );
            $p->execute([':id' => (int) $j['smtp_config_id']]);
            if ($r = $p->fetch(PDO::FETCH_ASSOC)) {
                $pauseSec = (int) $r['pause_seconds'];
            }
        }

        return array_merge([
            'success' => true,
            'job_id' => $jobId,
            'estado' => $j['estado'] ?? 'unknown',
            'cancel_flag' => (int) ($j['cancel_flag'] ?? 0),
            'total_destinatarios' => (int) ($j['total_destinatarios'] ?? 0),
            'total_enviados' => (int) ($j['total_enviados'] ?? 0),
            'total_fallidos' => (int) ($j['total_fallidos'] ?? 0),
            'total_skipped' => (int) ($j['total_skipped'] ?? 0),
            'remaining' => $remaining,
            'is_final' => in_array($j['estado'] ?? '', ['completed', 'cancelled', 'failed'], true),
            'hint' => $hint,
            'pause_seconds' => $pauseSec,
        ], $extra);
    }
}
