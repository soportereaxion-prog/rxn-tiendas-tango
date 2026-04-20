<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos\Services\RowRenderers;

/**
 * Contrato para renderers de filas de un "reporte de contenido".
 *
 * Cada renderer recibe las filas crudas de un reporte (con los alias
 * `{Entity}_{field}` que produce ReportQueryBuilder) y devuelve un bloque
 * HTML email-safe concatenado — apto para inyectar en el body de un mail
 * masivo reemplazando el placeholder `{{Bloque.html}}`.
 *
 * IMPORTANTE: el HTML producido debe ser:
 *   - Inline styles (clientes de mail filtran <style>).
 *   - Table-based layout (no flex, no grid).
 *   - Sin JS, sin fonts externas, sin <link>.
 *   - Compatible con Gmail, Outlook (web y desktop), Apple Mail.
 */
interface RowRenderer
{
    /**
     * @param list<array<string, mixed>> $rows Filas del query del reporte.
     * @return string HTML email-safe.
     */
    public function renderRows(array $rows): string;
}
