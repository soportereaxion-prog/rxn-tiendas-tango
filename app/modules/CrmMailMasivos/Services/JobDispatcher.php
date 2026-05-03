<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos\Services;

use App\Core\Database;
use App\Modules\CrmMailMasivos\JobRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * JobDispatcher: orquesta la creación de un envío masivo.
 *
 * Responsabilidades:
 *   1. Validar que reporte + plantilla + SMTP estén disponibles para la empresa/usuario.
 *   2. Ejecutar el query del reporte con LIMIT configurable → lista de destinatarios.
 *   3. Deduplicar emails y filtrar los inválidos (silenciosamente).
 *   4. Insertar la cabecera en `crm_mail_jobs` + los items en `crm_mail_job_items`.
 *   5. Disparar webhook a n8n con el job_id para que arranque el workflow.
 *   6. Si el webhook falla, marcar el job como 'failed' y devolver el error.
 *
 * IMPORTANTE: El dispatcher NO envía los mails. n8n los envía.
 * Acá solo se "cocina" el job para que n8n lo encuentre en DB y empiece a procesar.
 */
class JobDispatcher
{
    // Límite duro de destinatarios por job. Protege la DB y la instancia de n8n.
    public const MAX_RECIPIENTS = 5000;

    private JobRepository $repo;
    private ReportMetamodel $meta;
    private ReportQueryBuilder $builder;
    private PDO $db;

    public function __construct(
        ?JobRepository $repo = null,
        ?ReportMetamodel $meta = null
    ) {
        $this->repo = $repo ?? new JobRepository();
        $this->meta = $meta ?? new ReportMetamodel();
        $this->builder = new ReportQueryBuilder($this->meta);
        $this->db = Database::getConnection();
    }

    /**
     * Preview de destinatarios: ejecuta el query del reporte y devuelve
     * emails únicos + row data. NO crea job.
     *
     * @return array{count: int, mails: list<array{email: string, name: ?string, data: array<string, mixed>}>, sql_debug: string}
     */
    public function previewRecipients(int $reportId, int $empresaId, int $limit = self::MAX_RECIPIENTS): array
    {
        $report = $this->loadReport($reportId, $empresaId);
        $config = json_decode((string) $report['config_json'], true);
        if (!is_array($config)) {
            throw new InvalidArgumentException('Config del reporte ilegible.');
        }

        $built = $this->builder->build($config, $empresaId, $limit);

        $stmt = $this->db->prepare($built['sql']);
        foreach ($built['params'] as $name => $value) {
            $stmt->bindValue($name, $value, $this->pdoType($value));
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mailAlias = $built['mail_target']['alias'];
        $seen = [];
        $mails = [];

        foreach ($rows as $row) {
            $email = trim((string) ($row[$mailAlias] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $emailLower = mb_strtolower($email);
            if (isset($seen[$emailLower])) continue; // dedup
            $seen[$emailLower] = true;

            $mails[] = [
                'email' => $email,
                'name' => $this->guessRecipientName($row),
                'data' => $row,
            ];
        }

        return [
            'count' => count($mails),
            'mails' => $mails,
            'sql_debug' => $built['sql'],
        ];
    }

    /**
     * Crea el job + items y dispara el webhook a n8n.
     *
     * Si se pasa `$contentReportId > 0`, se ejecuta el "reporte de contenido"
     * vía BlockRenderer y su HTML reemplaza al placeholder `{{Bloque.html}}`
     * en el body del template ANTES de congelar el `body_snapshot`. Así el
     * contenido broadcast viaja igual para todos los destinatarios y el
     * BatchProcessor no necesita enterarse de nada especial.
     *
     * @return array{job_id: int, total_destinatarios: int, n8n_response: ?array, warnings: list<string>}
     */
    public function dispatch(
        int $empresaId,
        int $usuarioId,
        int $reportId,
        int $templateId,
        int $contentReportId = 0
    ): array {
        $ctx = $this->repo->loadJobContext($empresaId, $usuarioId, $reportId, $templateId);

        if (!$ctx['report'])   throw new InvalidArgumentException('Reporte no encontrado o sin acceso.');
        if (!$ctx['template']) throw new InvalidArgumentException('Plantilla no encontrada o sin acceso.');
        if (!$ctx['smtp'])     throw new InvalidArgumentException('No tenés una configuración SMTP para envíos masivos. Configurala en Mi Perfil.');
        if (empty($ctx['smtp']['activo'])) throw new InvalidArgumentException('Tu SMTP para envíos masivos está inactivo. Activalo en Mi Perfil.');

        // 1. Destinatarios
        $preview = $this->previewRecipients($reportId, $empresaId, self::MAX_RECIPIENTS);
        if ($preview['count'] === 0) {
            throw new InvalidArgumentException('El reporte no devolvió destinatarios. Revisá los filtros o el mail_field.');
        }

        // 2. Resolver bloque de contenido broadcast (opcional).
        //    Renderizamos UNA sola vez acá y reemplazamos el placeholder en el
        //    body del template. Así el snapshot queda con el HTML final listo
        //    y el BatchProcessor sigue reemplazando sólo las variables per-row.
        $bodyHtml = (string) $ctx['template']['body_html'];
        if ($contentReportId > 0) {
            $renderer = new BlockRenderer($this->meta);
            $blockHtml = $renderer->renderContentReport($contentReportId, $empresaId);
            $bodyHtml = str_replace('{{Bloque.html}}', $blockHtml, $bodyHtml);
        }

        // Resolver placeholders globales de la suite ANTES del snapshot —
        // no dependen de destinatario y deben quedar fijos en el body congelado.
        $bodyHtml = SuitePlaceholderResolver::resolve($bodyHtml);
        $asuntoFinal = SuitePlaceholderResolver::resolve((string) $ctx['template']['asunto']);

        // 3. Crear cabecera job
        $jobId = $this->repo->createJob([
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioId,
            'report_id' => $reportId,
            'content_report_id' => $contentReportId > 0 ? $contentReportId : null,
            'template_id' => $templateId,
            'smtp_config_id' => (int) $ctx['smtp']['id'],
            'asunto' => $asuntoFinal,
            'body_snapshot' => $bodyHtml,
            'attachments_json' => null, // Fase 4b
            'total_destinatarios' => $preview['count'],
        ]);

        // 3. Crear items con tracking_token único
        $items = [];
        foreach ($preview['mails'] as $m) {
            $items[] = [
                'recipient_email' => $m['email'],
                'recipient_name' => $m['name'],
                'recipient_data_json' => json_encode($m['data'], JSON_UNESCAPED_UNICODE),
                'tracking_token' => $this->generateTrackingToken(),
            ];
        }
        $this->repo->createJobItems($jobId, $empresaId, $items);

        // 4. Disparar webhook a n8n
        $warnings = [];
        $n8nResponse = null;

        try {
            $n8nResponse = $this->triggerN8nWebhook($jobId);
        } catch (RuntimeException $e) {
            // El job queda en 'queued' — el workflow lo puede tomar por
            // pollling (si está configurado) o el usuario puede reintentar.
            $warnings[] = 'El webhook de n8n no respondió: ' . $e->getMessage()
                . '. El job quedó en cola — podés reintentar desde el monitor.';
            error_log('[JobDispatcher] Webhook n8n falló para job #' . $jobId . ': ' . $e->getMessage());
        }

        return [
            'job_id' => $jobId,
            'total_destinatarios' => $preview['count'],
            'n8n_response' => $n8nResponse,
            'warnings' => $warnings,
        ];
    }

    // ──────────────────────────────────────────────
    // INTERNOS
    // ──────────────────────────────────────────────

    /**
     * Arma el payload y hace POST al webhook de n8n.
     *
     * Config leída de $_ENV:
     *   N8N_MAIL_MASIVOS_WEBHOOK_URL   — URL completa del webhook
     *   N8N_MAIL_MASIVOS_WEBHOOK_TOKEN — (opcional) token que n8n valida
     *
     * El payload es intencionalmente mínimo: solo lleva `job_id`. n8n hace
     * todo el resto consultando MySQL directamente. Así evitamos pasar
     * credenciales SMTP en el wire; n8n las lee de DB dentro del workflow.
     *
     * @return array<string, mixed>
     */
    private function triggerN8nWebhook(int $jobId): array
    {
        $url = (string) ($_ENV['N8N_MAIL_MASIVOS_WEBHOOK_URL'] ?? getenv('N8N_MAIL_MASIVOS_WEBHOOK_URL') ?: '');
        $token = (string) ($_ENV['N8N_MAIL_MASIVOS_WEBHOOK_TOKEN'] ?? getenv('N8N_MAIL_MASIVOS_WEBHOOK_TOKEN') ?: '');

        if ($url === '') {
            throw new RuntimeException('N8N_MAIL_MASIVOS_WEBHOOK_URL no está configurada en .env');
        }

        $payload = json_encode([
            'job_id' => $jobId,
            'source' => 'rxn_suite',
            'fired_at' => date('c'),
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => array_filter([
                'Content-Type: application/json',
                'Accept: application/json',
                $token !== '' ? 'X-RXN-Token: ' . $token : null,
            ]),
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('CURL error: ' . $err);
        }
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('HTTP ' . $code . ' del webhook n8n');
        }

        $decoded = $body ? json_decode((string) $body, true) : null;
        return is_array($decoded) ? $decoded : ['raw' => (string) $body];
    }

    private function loadReport(int $reportId, int $empresaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM crm_mail_reports
             WHERE id = :id AND empresa_id = :emp AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([':id' => $reportId, ':emp' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('Reporte no encontrado o sin acceso.');
        }
        return $row;
    }

    private function guessRecipientName(array $row): ?string
    {
        // Heurística: preferir "razon_social" > "nombre+apellido" > cualquier
        // "nombre*" del row. No crítico — es para el header "To:".
        foreach ($row as $alias => $value) {
            if (!is_string($value) || $value === '') continue;
            if (str_contains($alias, 'razon_social')) return $value;
        }
        $nombre = null; $apellido = null;
        foreach ($row as $alias => $value) {
            if (!is_string($value) || $value === '') continue;
            $lower = mb_strtolower($alias);
            if (str_ends_with($lower, '_nombre') && $nombre === null) $nombre = $value;
            if (str_ends_with($lower, '_apellido') && $apellido === null) $apellido = $value;
        }
        if ($nombre && $apellido) return trim($nombre . ' ' . $apellido);
        return $nombre;
    }

    private function generateTrackingToken(): string
    {
        // 48 chars hex (24 bytes random) — cabe en VARCHAR(64).
        return bin2hex(random_bytes(24));
    }

    private function pdoType($value): int
    {
        if (is_int($value)) return PDO::PARAM_INT;
        if (is_bool($value)) return PDO::PARAM_BOOL;
        if ($value === null) return PDO::PARAM_NULL;
        return PDO::PARAM_STR;
    }
}
