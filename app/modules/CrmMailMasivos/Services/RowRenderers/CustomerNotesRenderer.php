<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos\Services\RowRenderers;

use DateTime;
use Throwable;

/**
 * Renderer de customer_notes como bloque HTML email-safe.
 *
 * Cada fila se pinta como una "card" con un header de categoría (color + label)
 * y el cuerpo de la novedad. El output es una serie de tablas apiladas que los
 * clientes de mail (Gmail, Outlook, Apple Mail) renderizan con layout estable.
 *
 * Convenciones visuales:
 *   - `feature`      → verde  (nueva capacidad)
 *   - `mejora`       → azul   (mejora sobre algo existente)
 *   - `seguridad`    → dorado (refuerzo de seguridad; lenguaje de capacidad)
 *   - `performance`  → violeta(mejora de rendimiento)
 *   - `fix_visible`  → gris   (ajuste visible para el cliente)
 *
 * Ordenamiento: por `published_at` descendente (más nuevas primero).
 * Formato de fecha: es-AR ("12 de abril de 2026").
 */
class CustomerNotesRenderer implements RowRenderer
{
    private const CATEGORY_STYLE = [
        'feature' => [
            'label' => 'NUEVO',
            'bg' => '#16a34a',
            'border' => '#15803d',
        ],
        'mejora' => [
            'label' => 'MEJORA',
            'bg' => '#2563eb',
            'border' => '#1d4ed8',
        ],
        'seguridad' => [
            'label' => 'SEGURIDAD',
            'bg' => '#b45309',
            'border' => '#92400e',
        ],
        'performance' => [
            'label' => 'PERFORMANCE',
            'bg' => '#7c3aed',
            'border' => '#6d28d9',
        ],
        'fix_visible' => [
            'label' => 'AJUSTE',
            'bg' => '#4b5563',
            'border' => '#374151',
        ],
    ];

    private const MESES_ES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    public function renderRows(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        // Ordenamos por published_at desc (las que tienen published_at primero;
        // el resto queda al final por orden del query)
        usort($rows, static function (array $a, array $b): int {
            $da = (string) ($a['CustomerNotes_published_at'] ?? '');
            $db = (string) ($b['CustomerNotes_published_at'] ?? '');
            return strcmp($db, $da);
        });

        $cards = '';
        foreach ($rows as $row) {
            $cards .= $this->renderCard($row);
        }

        // Wrapper externo con ancho máximo de 600px — estándar para emails.
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
             . ' style="border-collapse:collapse;background:transparent;">'
             . '<tr><td align="center" style="padding:0;">'
             . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
             . ' style="max-width:600px;border-collapse:collapse;">'
             . '<tr><td style="padding:0;">' . $cards . '</td></tr>'
             . '</table></td></tr></table>';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function renderCard(array $row): string
    {
        $title = trim((string) ($row['CustomerNotes_title'] ?? ''));
        $bodyHtml = (string) ($row['CustomerNotes_body_html'] ?? '');
        $category = (string) ($row['CustomerNotes_category'] ?? 'fix_visible');
        $publishedAt = (string) ($row['CustomerNotes_published_at'] ?? '');
        $createdAt = (string) ($row['CustomerNotes_created_at'] ?? '');

        $style = self::CATEGORY_STYLE[$category] ?? self::CATEGORY_STYLE['fix_visible'];
        $dateStr = $this->formatDateEsAr($publishedAt !== '' ? $publishedAt : $createdAt);

        $titleHtml = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // body_html ya es HTML controlado por el admin (se edita desde
        // herramientas internas) — lo pasamos tal cual. No aceptamos HTML
        // de input de usuario público en ningún lado de este módulo.
        $bodyHtml = $bodyHtml !== '' ? $bodyHtml : '<em style="color:#9ca3af;">Sin descripción.</em>';

        $fontFamily = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
             . ' style="border-collapse:separate;border-spacing:0;margin:0 0 16px 0;">'
             // Header de categoría
             . '<tr><td style="padding:10px 18px;background:' . $style['bg'] . ';'
             . 'border-top-left-radius:8px;border-top-right-radius:8px;'
             . 'color:#ffffff;font-family:' . $fontFamily . ';font-size:11px;'
             . 'font-weight:700;letter-spacing:1px;">'
             . $style['label']
             . ($dateStr !== '' ? ' <span style="font-weight:400;opacity:.85;">· ' . $dateStr . '</span>' : '')
             . '</td></tr>'
             // Cuerpo de la card
             . '<tr><td style="padding:18px 20px 22px 20px;background:#ffffff;'
             . 'border:1px solid ' . $style['border'] . ';border-top:none;'
             . 'border-bottom-left-radius:8px;border-bottom-right-radius:8px;'
             . 'font-family:' . $fontFamily . ';color:#111827;">'
             . '<h3 style="margin:0 0 10px 0;font-size:18px;line-height:1.3;'
             . 'font-weight:700;color:#111827;">' . $titleHtml . '</h3>'
             . '<div style="font-size:14px;line-height:1.6;color:#374151;">'
             . $bodyHtml
             . '</div>'
             . '</td></tr>'
             . '</table>';
    }

    private function formatDateEsAr(string $raw): string
    {
        if ($raw === '' || str_starts_with($raw, '0000')) {
            return '';
        }
        try {
            $d = new DateTime($raw);
            $m = (int) $d->format('n');
            $mesName = self::MESES_ES[$m] ?? '';
            return $d->format('j') . ' de ' . $mesName . ' de ' . $d->format('Y');
        } catch (Throwable $e) {
            return '';
        }
    }
}
