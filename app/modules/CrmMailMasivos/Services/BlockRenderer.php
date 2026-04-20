<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos\Services;

use App\Core\Database;
use App\Modules\CrmMailMasivos\Services\RowRenderers\CustomerNotesRenderer;
use App\Modules\CrmMailMasivos\Services\RowRenderers\RowRenderer;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * BlockRenderer — motor de "reportes de contenido".
 *
 * Cuando un envío masivo se dispara con un `content_report_id` además del
 * reporte de destinatarios, este servicio:
 *
 *   1. Carga el reporte de contenido (valida empresa_id + soft delete).
 *   2. Corre el query del reporte vía ReportQueryBuilder con
 *      `requireMailTarget=false` — el reporte de contenido NO tiene
 *      destinatarios; sus filas son los bloques de contenido a mostrar.
 *   3. Despacha al RowRenderer adecuado según la `root_entity` del reporte.
 *   4. Devuelve un único string HTML que el JobDispatcher inyecta en el
 *      `body_snapshot` reemplazando el placeholder `{{Bloque.html}}`.
 *
 * Extensibilidad: para sumar una entidad nueva como fuente de contenido
 * (ej. listas de precios, promos, artículos destacados), basta con:
 *   - Declararla en `config/entities.php` (como CustomerNotes).
 *   - Crear `Services/RowRenderers/<Entidad>Renderer.php` que implemente RowRenderer.
 *   - Agregar la entrada correspondiente en BlockRenderer::RENDERERS.
 */
class BlockRenderer
{
    /**
     * Map root_entity → FQN del renderer asociado.
     * @var array<string, class-string<RowRenderer>>
     */
    private const RENDERERS = [
        'CustomerNotes' => CustomerNotesRenderer::class,
    ];

    private PDO $db;
    private ReportMetamodel $meta;
    private ReportQueryBuilder $builder;

    public function __construct(?ReportMetamodel $meta = null)
    {
        $this->db = Database::getConnection();
        $this->meta = $meta ?? new ReportMetamodel();
        $this->builder = new ReportQueryBuilder($this->meta);
    }

    /**
     * Lista las root_entity soportadas como "contenido". Útil para el UI de
     * crear-envío (filtrar qué reportes se ofrecen en el selector de contenido).
     *
     * @return list<string>
     */
    public static function knownContentEntities(): array
    {
        return array_keys(self::RENDERERS);
    }

    /**
     * True si la entidad está registrada como fuente de contenido (no requiere
     * mail_field). Se usa en los callsites del builder que deciden si un
     * reporte puede guardarse/previsualizarse sin destinatario.
     */
    public static function isContentEntity(string $entity): bool
    {
        return isset(self::RENDERERS[$entity]);
    }

    /**
     * Renderiza un reporte de contenido a HTML email-safe.
     *
     * Si el reporte no devuelve filas, retorna string vacío — el placeholder
     * `{{Bloque.html}}` se reemplaza por "" y el mail viaja sin bloque.
     */
    public function renderContentReport(int $reportId, int $empresaId): string
    {
        $report = $this->loadReport($reportId, $empresaId);

        $rootEntity = (string) $report['root_entity'];
        if (!isset(self::RENDERERS[$rootEntity])) {
            throw new RuntimeException(
                "El reporte #{$reportId} usa root_entity '{$rootEntity}' pero "
              . "no hay RowRenderer registrado para esa entidad. Registralo en "
              . BlockRenderer::class . '::RENDERERS.'
            );
        }

        $config = json_decode((string) $report['config_json'], true);
        if (!is_array($config)) {
            throw new RuntimeException("config_json del reporte #{$reportId} ilegible");
        }

        $built = $this->builder->build($config, $empresaId, 0, false);

        $stmt = $this->db->prepare($built['sql']);
        foreach ($built['params'] as $name => $value) {
            $stmt->bindValue($name, $value, $this->pdoType($value));
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($rows)) {
            return '';
        }

        /** @var RowRenderer $renderer */
        $renderer = new (self::RENDERERS[$rootEntity])();
        return $renderer->renderRows($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadReport(int $reportId, int $empresaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nombre, root_entity, config_json
             FROM crm_mail_reports
             WHERE id = :id AND empresa_id = :emp AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([':id' => $reportId, ':emp' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException(
                "Reporte de contenido #{$reportId} no encontrado o sin acceso."
            );
        }
        return $row;
    }

    private function pdoType(mixed $value): int
    {
        if (is_int($value)) return PDO::PARAM_INT;
        if (is_bool($value)) return PDO::PARAM_BOOL;
        if ($value === null) return PDO::PARAM_NULL;
        return PDO::PARAM_STR;
    }
}
