<?php
/**
 * email_render.php — Template HTML para cuerpo de email compatible con Outlook
 *
 * Diferencias clave respecto a document_render.php (preview/PDF):
 *  - Sin position:absolute (Outlook/Word lo ignora por completo)
 *  - Sin unidades mm (Outlook solo entiende px/pt de forma confiable)
 *  - Sin object-fit, sin box-sizing (no soportados)
 *  - Layout basado en <table> (el estándar compatible con todos los clientes de correo)
 *  - Estilos tipográficos del canvas preservados vía inline styles
 *  - Dimensiones de imagen respetadas (w_mm convertido a px a 96 DPI)
 *  - Objetos ordenados por y_mm para flujo vertical lógico
 *
 * Variables esperadas:
 *  @var array $page             Configuración de página (background_color, width_mm, etc.)
 *  @var array $renderedObjects  Objetos procesados por PrintFormRenderer (con x_mm,y_mm,w_mm,h_mm)
 */

declare(strict_types=1);

// 1mm = 3.7795275591px a 96 DPI (estándar web)
$mmToPx = static function (float $mm): int {
    return (int) round($mm * 3.7795275591);
};

// Ancho máximo del contenedor email en px (estándar industria = 600px)
$emailContainerPx = 600;

// Propiedades de tipografía que son seguras y compatibles con Outlook
// (se extraen de inner_style del renderer para preservar el diseño del canvas)
$extractTypographyStyle = static function (string $innerStyle): string {
    $safeProps = ['font-family', 'font-size', 'font-weight', 'color', 'text-align'];
    $parts = array_map('trim', explode(';', $innerStyle));
    $safe  = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        foreach ($safeProps as $prop) {
            if (stripos($part, $prop . ':') !== false) {
                // Normalizar: convertir pt a pt (Outlook sí soporta pt para font-size)
                $safe[] = $part;
                break;
            }
        }
    }
    return implode('; ', $safe);
};

// Ordenar objetos de arriba hacia abajo (y_mm ascendente) para flujo vertical lógico
$emailObjects = $renderedObjects ?? [];
usort($emailObjects, static function (array $a, array $b): int {
    return ($a['y_mm'] ?? 0) <=> ($b['y_mm'] ?? 0);
});

$bgColor = htmlspecialchars((string) ($page['background_color'] ?? '#ffffff'));
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">

    <!--[if mso]><table width="600" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->

    <table
        width="600"
        cellpadding="0"
        cellspacing="0"
        border="0"
        align="center"
        style="width:600px; max-width:600px; background-color:<?= $bgColor ?>; margin:0 auto;"
    >

        <?php foreach ($emailObjects as $obj): ?>
            <?php
            $type       = (string) ($obj['type'] ?? '');
            $innerStyle = (string) ($obj['inner_style'] ?? '');
            $content    = (string) ($obj['content'] ?? '');
            $wPx        = $mmToPx((float) ($obj['w_mm'] ?? 0));
            $hPx        = $mmToPx((float) ($obj['h_mm'] ?? 0));

            // Elementos puramente decorativos/estructurales sin equivalente email semántico
            if ($type === 'rect') {
                continue;
            }
            ?>

            <tr>
                <td align="left" valign="top" style="padding:3px 12px;">

                    <?php if ($type === 'text'): ?>
                        <?php
                        // Extraer solo tipografía del canvas — ignorar position, white-space, overflow, padding en mm
                        $typoStyle = $extractTypographyStyle($innerStyle);
                        ?>
                        <div style="<?= htmlspecialchars($typoStyle) ?>; margin:0; padding:4px 0; line-height:1.4;">
                            <?= nl2br(\App\Shared\Helpers\HtmlSanitizer::allowSafeInlineHtml($content)) ?>
                        </div>

                    <?php elseif ($type === 'image' && $content !== ''): ?>
                        <?php
                        // Respetar dimensiones del canvas. Cap al container de 600px (menos padding lateral de 24px)
                        $maxImgW = $emailContainerPx - 24;
                        $imgW    = $wPx > 0 ? min($wPx, $maxImgW) : $maxImgW;
                        // Si se especificó alto en canvas y el ancho no fue recortado, preservar proporción
                        $imgH = ($hPx > 0 && $wPx > 0 && $wPx <= $maxImgW) ? $hPx : 0;
                        ?>
                        <img
                            src="<?= htmlspecialchars($content) ?>"
                            width="<?= $imgW ?>"
                            <?php if ($imgH > 0): ?>height="<?= $imgH ?>"<?php endif; ?>
                            style="display:block; border:0; outline:none; text-decoration:none; -ms-interpolation-mode:bicubic; max-width:100%;"
                            alt=""
                        >

                    <?php elseif ($type === 'line'): ?>
                        <?php
                        $strokeColor = htmlspecialchars((string) ($obj['stroke_color'] ?? '#cccccc'));
                        $strokePx    = max(1, $mmToPx((float) ($obj['stroke_width_mm'] ?? 0.3)));
                        $isVertical  = !empty($obj['is_vertical']);
                        ?>
                        <?php if (!$isVertical): ?>
                            <hr style="border:none; border-top:<?= $strokePx ?>px solid <?= $strokeColor ?>; margin:4px 0; height:0;">
                        <?php endif; ?>
                        <?php // Líneas verticales no tienen equivalente semántico limpio en email — se omiten ?>

                    <?php elseif ($type === 'table_repeater'): ?>
                        <?php
                        $columns     = (array) ($obj['columns'] ?? []);
                        $rows        = (array) ($obj['rows'] ?? []);
                        $showHeader  = !empty($obj['show_header']);
                        // Extraer tipografía de la tabla del canvas
                        $tableTypo   = $extractTypographyStyle((string) ($obj['table_style'] ?? ''));
                        // Para celdas: extraer border y padding del cell_style, reemplazando mm por px
                        $rawCellStyle   = (string) ($obj['cell_style'] ?? '');
                        $rawHeaderStyle = (string) ($obj['header_cell_style'] ?? '');
                        // Normalizar bordes: reemplazar 0.2mm → 1px para compatibilidad Outlook
                        $emailCellStyle   = preg_replace('/[\d.]+mm\s+solid/i', '1px solid', $rawCellStyle);
                        $emailHeaderStyle = preg_replace('/[\d.]+mm\s+solid/i', '1px solid', $rawHeaderStyle);
                        // Quitar propiedades incompatibles de las celdas
                        foreach (['text-overflow', 'white-space', 'overflow'] as $badProp) {
                            $emailCellStyle   = preg_replace('/\b' . $badProp . '\s*:[^;]+;?\s*/i', '', $emailCellStyle);
                            $emailHeaderStyle = preg_replace('/\b' . $badProp . '\s*:[^;]+;?\s*/i', '', $emailHeaderStyle);
                        }
                        // Quitar padding en mm de las celdas y reemplazar con px equivalente
                        $emailCellStyle   = preg_replace('/padding\s*:[^;]+;?\s*/i', 'padding:3px 6px; ', $emailCellStyle);
                        $emailHeaderStyle = preg_replace('/padding\s*:[^;]+;?\s*/i', 'padding:3px 6px; ', $emailHeaderStyle);
                        ?>
                        <table
                            width="100%"
                            cellpadding="0"
                            cellspacing="0"
                            border="0"
                            style="<?= htmlspecialchars($tableTypo) ?>; width:100%; border-collapse:collapse;"
                        >
                            <?php if ($showHeader && !empty($columns)): ?>
                                <thead>
                                    <tr>
                                        <?php foreach ($columns as $col): ?>
                                            <th style="<?= htmlspecialchars((string) $emailHeaderStyle) ?>; text-align:<?= htmlspecialchars((string) ($col['align'] ?? 'left')) ?>; width:<?= number_format((float) ($col['width_percent'] ?? 0), 2) ?>%;">
                                                <?= htmlspecialchars((string) ($col['label'] ?? '')) ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                            <?php endif; ?>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <?php foreach ($columns as $col): ?>
                                            <?php $cellKey = (string) ($col['key'] ?? ''); ?>
                                            <td style="<?= htmlspecialchars((string) $emailCellStyle) ?>; text-align:<?= htmlspecialchars((string) ($col['align'] ?? 'left')) ?>;">
                                                <?= htmlspecialchars((string) ($row[$cellKey] ?? '')) ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    <?php endif; ?>

                </td>
            </tr>

        <?php endforeach; ?>

    </table>

    <!--[if mso]></td></tr></table><![endif]-->

</body>
</html>
